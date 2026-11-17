(() => {
  if (window.parent === window) {
    return;
  }

  const styles = document.createElement("style");
  styles.textContent = `
    .inlay-preview-outline { position: fixed; z-index: 2147483647; pointer-events: none; box-sizing: border-box; border: 2px solid var(--inlay-preview-colour, #2f5fd6); border-radius: 2px; }
    .inlay-preview-outline[hidden] { display: none; }
    .inlay-preview-outline--hover { border-style: dashed; }
    .inlay-preview-outline__label { position: absolute; top: 0; left: 0; padding: 3px 8px; background: var(--inlay-preview-colour, #2f5fd6); color: #fff; font: 600 12px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; border-bottom-right-radius: 4px; white-space: nowrap; }
    [data-inlay-section] * { cursor: pointer !important; }
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

  // Sections are display:contents wrappers with no box, so measure their contents.
  const boundsOf = (section) => {
    const range = document.createRange();
    range.selectNodeContents(section);

    return range.getBoundingClientRect();
  };

  const place = (box, section) => {
    if (!section || !section.isConnected) {
      box.hidden = true;

      return;
    }

    const rect = boundsOf(section);
    box.hidden = false;
    box.style.top = `${rect.top}px`;
    box.style.left = `${rect.left}px`;
    box.style.width = `${rect.width}px`;
    box.style.height = `${rect.height}px`;
    box.firstElementChild.textContent = section.dataset.inlaySectionLabel || "";
  };

  const update = () => {
    place(selectedBox, selected);
    place(hoverBox, hovered === selected ? null : hovered);
  };

  const sectionOf = (target) =>
    target instanceof Element ? target.closest("[data-inlay-section]") : null;

  const start = () => {
    document.head.appendChild(styles);
    hoverBox = outline("hover");
    selectedBox = outline("selected");

    document.addEventListener("mouseover", (event) => {
      hovered = sectionOf(event.target);
      update();
    });

    document.documentElement.addEventListener("mouseleave", () => {
      hovered = null;
      update();
    });

    document.addEventListener(
      "click",
      (event) => {
        const section = sectionOf(event.target);

        if (!section) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        selected = section;
        update();
        window.parent.postMessage(
          { type: "inlay:select-section", id: Number(section.dataset.inlaySection) },
          window.location.origin,
        );
      },
      true,
    );

    let reportScroll = null;

    window.addEventListener(
      "scroll",
      () => {
        update();
        clearTimeout(reportScroll);
        reportScroll = setTimeout(
          () => window.parent.postMessage({ type: "inlay:scrolled", y: window.scrollY }, window.location.origin),
          100,
        );
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

      selected = event.data.id
        ? document.querySelector(`[data-inlay-section="${Number(event.data.id)}"]`)
        : null;

      if (typeof event.data.restoreScroll === "number") {
        window.scrollTo({ top: event.data.restoreScroll, behavior: "instant" });
      }

      if (selected && event.data.scroll) {
        selected.firstElementChild?.scrollIntoView({ section: "start", behavior: "smooth" });
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
