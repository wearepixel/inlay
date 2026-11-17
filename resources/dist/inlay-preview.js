(() => {
  if (window.parent === window) {
    return;
  }

  const styles = document.createElement("style");
  styles.textContent = `
    .inlay-preview-outline { position: fixed; z-index: 2147483647; pointer-events: none; box-sizing: border-box; border: 2px solid var(--inlay-preview-colour, #2f5fd6); border-radius: 2px; }
    .inlay-preview-outline[hidden] { display: none; }
    .inlay-preview-outline--hover { border-style: dashed; }
    .inlay-preview-outline__label { position: absolute; bottom: 100%; left: -2px; padding: 2px 8px; background: var(--inlay-preview-colour, #2f5fd6); color: #fff; font: 600 12px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; border-radius: 4px 4px 0 0; white-space: nowrap; }
    [data-inlay-section], [data-inlay-section] *, [data-inlay-block] { cursor: pointer !important; }
  `;

  let hoverBox;
  let selectedBox;
  let hovered = null;
  let selected = null;

  const outline = (modifier) => {
    const box = document.createElement("div");
    box.className = `inlay-preview-outline inlay-preview-outline--${modifier}`;
    box.hidden = true;
    box.innerHTML = '<span class="inlay-preview-outline__label"></span>';
    document.body.appendChild(box);

    return box;
  };

  // A display:contents wrapper (used when markup has several root elements)
  // has no box of its own, so its contents are measured instead.
  const boundsOf = (element) => {
    if (getComputedStyle(element).display !== "contents") {
      return element.getBoundingClientRect();
    }

    const range = document.createRange();
    range.selectNodeContents(element);

    return range.getBoundingClientRect();
  };

  const place = (box, element) => {
    if (!element || !element.isConnected) {
      box.hidden = true;

      return;
    }

    const rect = boundsOf(element);
    box.hidden = false;
    box.style.top = `${rect.top}px`;
    box.style.left = `${rect.left}px`;
    box.style.width = `${rect.width}px`;
    box.style.height = `${rect.height}px`;
    box.firstElementChild.textContent =
      element.dataset.inlayBlockLabel || element.dataset.inlaySectionLabel || "";
  };

  const update = () => {
    place(selectedBox, selected);
    place(hoverBox, hovered === selected ? null : hovered);
  };

  // The innermost selectable thing: a block if the pointer is inside one, else its section.
  const selectableAt = (target) =>
    target instanceof Element ? target.closest("[data-inlay-block], [data-inlay-section]") : null;

  const findSelected = (sectionId, blockId) => {
    if (!sectionId) {
      return null;
    }

    const section = document.querySelector(`[data-inlay-section="${Number(sectionId)}"]`);

    if (!section || !blockId) {
      return section;
    }

    for (const block of section.querySelectorAll("[data-inlay-block]")) {
      if (block.dataset.inlayBlock === String(blockId)) {
        return block;
      }
    }

    return section;
  };

  // A smooth scroll started while images are still loading gets cut short,
  // so a scroll requested right after a reload waits for the page to load.
  const scrollToSelected = () => {
    if (document.readyState !== "complete") {
      window.addEventListener("load", scrollToSelected, { once: true });

      return;
    }

    const target = getComputedStyle(selected).display === "contents" ? selected.firstElementChild : selected;
    target?.scrollIntoView({ block: "start", behavior: "smooth" });

    // Report where the scroll ended even when nothing moved, so the editor
    // knows the requested scroll is done.
    setTimeout(reportScroll, 700);
  };

  const reportScroll = () =>
    window.parent.postMessage({ type: "inlay:scrolled", y: window.scrollY }, window.location.origin);

  const sectionIdOf = (element) =>
    Number(element.closest("[data-inlay-section]")?.dataset.inlaySection);

  const start = () => {
    document.head.appendChild(styles);
    hoverBox = outline("hover");
    selectedBox = outline("selected");

    document.addEventListener("mouseover", (event) => {
      hovered = selectableAt(event.target);
      update();
    });

    document.documentElement.addEventListener("mouseleave", () => {
      hovered = null;
      update();
    });

    document.addEventListener(
      "click",
      (event) => {
        const clicked = selectableAt(event.target);

        if (!clicked) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        selected = clicked;
        update();
        window.parent.postMessage(
          clicked.dataset.inlayBlock
            ? { type: "inlay:select-block", id: sectionIdOf(clicked), blockId: clicked.dataset.inlayBlock }
            : { type: "inlay:select-section", id: sectionIdOf(clicked) },
          window.location.origin,
        );
      },
      true,
    );

    let reportScrollSoon = null;

    window.addEventListener(
      "scroll",
      () => {
        update();
        clearTimeout(reportScrollSoon);
        reportScrollSoon = setTimeout(reportScroll, 100);
      },
      { passive: true, capture: true },
    );
    window.addEventListener("resize", update);

    window.addEventListener("message", (event) => {
      if (event.origin !== window.location.origin || event.source !== window.parent) {
        return;
      }

      if (event.data?.type !== "inlay:selected") {
        return;
      }

      if (event.data.colour) {
        document.documentElement.style.setProperty("--inlay-preview-colour", event.data.colour);
      }

      selected = findSelected(event.data.id, event.data.blockId);

      if (typeof event.data.restoreScroll === "number") {
        window.scrollTo({ top: event.data.restoreScroll, behavior: "instant" });
      }

      if (selected && event.data.scroll) {
        scrollToSelected();
      }

      update();
    });

    window.parent.postMessage({ type: "inlay:preview-ready" }, window.location.origin);
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
