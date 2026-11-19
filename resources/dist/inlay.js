/*
 * Inlay admin interactivity - small, hand-authored Alpine.js data
 * components. No external libraries, no CDN dependency; Alpine itself
 * ships bundled with Livewire, which the admin already depends on.
 */
document.addEventListener("alpine:init", () => {
  /**
   * The contextual sidebar's whole client-side state: which pane is
   * showing (the section tree, a selected section's settings, or
   * version history), plus the section tree's drag-to-reorder and
   * keyboard navigation. All one Alpine component (rather than tree state
   * and pane-switching state living in separate x-data scopes) so
   * pane-switching methods can reach the tree's own $refs and focus
   * methods directly, with no cross-component event plumbing needed.
   *
   * Switching panes is deliberately client-only where nothing server-side
   * needs to change - going back to the tree, or the tree/settings split
   * itself, doesn't touch selectedSectionId, so it never round-trips.
   * Server-driven changes to *which* section is selected (adding,
   * duplicating, an external session's delete, restoring a revision) still
   * have to come from the server - see the section-selected,
   * selection-cleared, and history-toggled events this listens for,
   * dispatched by PageEditor.
   */
  Alpine.data(
    "inlayEditorSidebar",
    (initialView = "tree", initialSelectedId = null, announcements = {}) => ({
      view: initialView,
      announcements,
      mobilePanel: "edit",
      lastSelectedId: initialSelectedId,
      dragging: null,
      orderBeforeDrag: [],
      dropped: false,
      width: 360,
      collapsed: false,
      resizing: false,
      previewScroll: 0,
      selectedBlockId: null,
      scrollOnReload: false,

      init() {
        const storedWidth = parseInt(
          localStorage.getItem("inlay-sidebar-width"),
          10,
        );

        if (!Number.isNaN(storedWidth)) {
          this.width = this.clampWidth(storedWidth);
        }

        this.collapsed =
          localStorage.getItem("inlay-sidebar-collapsed") === "1";

        this.selectedBlockId = this.$wire.selectedBlockId ?? null;
        this.listenToPreview();
        window.addEventListener("block-selected", (event) => this.onBlockSelected(event.detail.id ?? null));
        this.$watch("view", () => this.syncPreview(false));
      },

      /**
       * The server selected a block: one picked from the sidebar list, or
       * one just added or duplicated. A new block only appears once the
       * preview reloads, so the scroll to it is repeated after the reload.
       * A block clicked in the preview is already shown and is left alone.
       */
      onBlockSelected(id) {
        if (id === this.selectedBlockId) {
          return;
        }

        this.selectedBlockId = id;
        this.scrollOnReload = id !== null;
        this.syncPreview(id !== null);
      },

      previewFrame() {
        return this.$root.querySelector("[data-test=preview-frame]");
      },

      /**
       * The preview (inlay-preview.js) reports clicks on a section and
       * announces itself after every reload, since each settings change
       * re-renders it from scratch.
       */
      listenToPreview() {
        window.addEventListener("message", (event) => {
          const frame = this.previewFrame();

          if (
            event.origin !== window.location.origin ||
            !frame ||
            event.source !== frame.contentWindow
          ) {
            return;
          }

          // The preview can load more than once per change, so a pending
          // scroll to a new section stays pending until the preview has
          // actually scrolled.
          if (event.data?.type === "inlay:preview-ready") {
            this.syncPreview(this.scrollOnReload, this.scrollOnReload ? null : this.previewScroll);
          }

          if (event.data?.type === "inlay:scrolled") {
            this.previewScroll = Number(event.data.y) || 0;
            this.scrollOnReload = false;
          }

          if (event.data?.type === "inlay:select-section") {
            this.selectFromPreview(Number(event.data.id), null);
          }

          if (event.data?.type === "inlay:select-block") {
            this.selectFromPreview(Number(event.data.id), String(event.data.blockId));
          }
        });
      },

      /**
       * A click in the preview: the clicked thing is already on screen, so
       * the preview is not scrolled.
       */
      selectFromPreview(sectionId, blockId) {
        this.lastSelectedId = sectionId;
        this.selectedBlockId = blockId;
        this.view = "settings";
        this.syncPreview(false);

        if (blockId === null) {
          this.$wire.selectSection(sectionId);
        } else {
          this.$wire.selectBlock(sectionId, blockId);
        }
      },

      syncPreview(scroll, restoreScroll = null) {
        const frame = this.previewFrame();

        if (!frame?.contentWindow) {
          return;
        }

        const id = this.view === "settings" ? this.lastSelectedId : null;

        frame.contentWindow.postMessage(
          {
            type: "inlay:selected",
            id,
            blockId: id ? this.selectedBlockId : null,
            scroll,
            restoreScroll,
            colour: getComputedStyle(this.$root).getPropertyValue("--inlay-focus").trim(),
          },
          window.location.origin,
        );
      },

      clampWidth(width) {
        const max = Math.min(560, Math.round(window.innerWidth * 0.45));

        return Math.max(280, Math.min(max, width));
      },

      /**
       * Dragging the boundary between the contextual panel and the preview.
       * Listens on window (not the handle itself) for move/up so the drag
       * keeps tracking even if the pointer outruns the thin 6px handle.
       */
      startResize(event) {
        event.preventDefault();
        this.resizing = true;
        this.collapsed = false;

        const startX = event.clientX;
        const startWidth = this.width;

        const onMove = (moveEvent) => {
          this.width = this.clampWidth(
            startWidth + (moveEvent.clientX - startX),
          );
        };

        const onUp = () => {
          this.resizing = false;
          localStorage.setItem("inlay-sidebar-width", this.width);
          localStorage.setItem(
            "inlay-sidebar-collapsed",
            this.collapsed ? "1" : "0",
          );
          window.removeEventListener("mousemove", onMove);
          window.removeEventListener("mouseup", onUp);
        };

        window.addEventListener("mousemove", onMove);
        window.addEventListener("mouseup", onUp);
      },

      nudgeWidth(delta) {
        this.collapsed = false;
        this.width = this.clampWidth(this.width + delta);
        localStorage.setItem("inlay-sidebar-width", this.width);
      },

      toggleCollapsed() {
        this.collapsed = !this.collapsed;
        localStorage.setItem(
          "inlay-sidebar-collapsed",
          this.collapsed ? "1" : "0",
        );
      },

      /**
       * Adding or duplicating a section selects it just before the preview
       * reloads, so the scroll to it happens once the reload is done.
       */
      showNewSection(id) {
        this.showSettings(id);
        this.scrollOnReload = true;
      },

      /**
       * Called optimistically by a tree row's own click handler (no need to
       * wait on the round trip just to flip which pane is visible) and by
       * showNewSection(). Scrolls the preview to the section.
       */
      showSettings(id) {
        this.lastSelectedId = id;
        this.selectedBlockId = null;
        this.view = "settings";
        this.$nextTick(() => this.syncPreview(true));
      },

      backToTree() {
        this.view = "tree";
        this.$nextTick(() => this.focusRow(this.lastSelectedId));
      },

      /**
       * Page-level settings (title, public path, metadata, homepage,
       * publishing info) are a distinct context from a section's own
       * settings - reachable from the header regardless of which section, if
       * any, is currently selected - so this never touches
       * lastSelectedId, and backToTree() returns to the tree exactly as it
       * would from a section's settings.
       */
      showPageSettings() {
        this.view = "page-settings";
      },

      /**
       * Distinct from backToTree(): this responds to the server telling us
       * the previously-selected section is simply gone (deleted, or a
       * revision restore replaced every section), so there is no id left to
       * remember or focus - selecting it in the tree afterwards is
       * meaningless once it no longer exists.
       */
      toTree() {
        this.lastSelectedId = null;
        this.view = "tree";
      },

      onHistoryToggled(showing) {
        this.view = showing
          ? "history"
          : this.lastSelectedId
            ? "settings"
            : "tree";
      },

      /**
       * The picker has its own window-level Escape handler that closes it
       * (see inlaySectionPicker) - this defers to that first, so Escape
       * closes the picker before it ever navigates the sidebar out of
       * settings, exactly as it would if they were unrelated.
       */
      onEscape() {
        if (this.$wire.showingPicker) {
          return;
        }

        if (this.view === "settings" || this.view === "page-settings") {
          this.backToTree();
        } else if (this.view === "history") {
          this.$wire.toggleHistory();
        }
      },

      announcement() {
        if (this.view === "settings") {
          return this.announcements.settings;
        }

        if (this.view === "page-settings") {
          return this.announcements.page_settings;
        }

        if (this.view === "history") {
          return this.announcements.history;
        }

        return this.announcements.tree;
      },

      /*
       * Dragging a section moves its row through the list as the pointer
       * goes, so the gap it leaves shows exactly where it will land. The
       * new order is only saved on drop; letting go anywhere else puts the
       * rows back.
       */
      dragStart(event, id) {
        this.dragging = id;
        this.orderBeforeDrag = this.rowIds();
        this.dropped = false;
        event.dataTransfer.effectAllowed = "move";
        // Firefox will not start a drag that carries no data.
        event.dataTransfer.setData("text/plain", String(id));

        // Wait a frame so the browser's drag image is taken of the row as
        // it looks now, not of the faded placeholder.
        const row = event.currentTarget;
        requestAnimationFrame(() => row.classList.add("is-dragging"));
      },

      dragOver(event) {
        if (this.dragging === null) {
          return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = "move";

        const overRow = event.target.closest("[data-section-id]");
        const draggedRow = this.rowElement(this.dragging);

        if (!overRow || overRow === draggedRow || overRow.getAttribute("draggable") !== "true") {
          return;
        }

        // A row that is still sliding reports its in-between position, which
        // would make the dragged row jump back and forth.
        if (overRow.getAnimations().length > 0) {
          return;
        }

        const bounds = overRow.getBoundingClientRect();
        const pointerInLowerHalf = event.clientY > bounds.top + bounds.height / 2;
        const rowToInsertBefore = pointerInLowerHalf ? overRow.nextElementSibling : overRow;

        if (rowToInsertBefore === draggedRow || rowToInsertBefore === draggedRow.nextElementSibling) {
          return;
        }

        this.moveRowWithAnimation(draggedRow, rowToInsertBefore);
      },

      drop() {
        if (this.dragging === null) {
          return;
        }

        this.dropped = true;
        const ids = this.rowIds();

        if (ids.join(",") !== this.orderBeforeDrag.join(",")) {
          this.$wire.updateOrder(ids);
        }
      },

      dragEnd() {
        const draggedRow = this.rowElement(this.dragging);
        draggedRow?.classList.remove("is-dragging");

        if (!this.dropped) {
          this.restoreOrder(this.orderBeforeDrag);
        }

        this.dragging = null;
      },

      /*
       * FLIP: note where every row is, move the dragged row, then animate
       * each row from its old position to its new one.
       */
      moveRowWithAnimation(draggedRow, rowToInsertBefore) {
        const rows = Array.from(this.$refs.list.children);
        const topsBefore = new Map(rows.map((row) => [row, row.getBoundingClientRect().top]));

        this.$refs.list.insertBefore(draggedRow, rowToInsertBefore);

        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
          return;
        }

        for (const row of rows) {
          const distance = topsBefore.get(row) - row.getBoundingClientRect().top;

          if (distance !== 0) {
            row.animate(
              [{ transform: `translateY(${distance}px)` }, { transform: "translateY(0)" }],
              { duration: 160, easing: "cubic-bezier(0.2, 0, 0, 1)" },
            );
          }
        }
      },

      restoreOrder(ids) {
        for (const id of ids) {
          const row = this.rowElement(id);

          if (row) {
            this.$refs.list.appendChild(row);
          }
        }
      },

      rowElement(id) {
        return this.$refs.list.querySelector(`[data-section-id="${id}"]`);
      },

      rowIds() {
        return Array.from(this.$refs.list.children).map((el) =>
          parseInt(el.dataset.sectionId, 10),
        );
      },

      focusRow(id) {
        this.rowElement(id)?.focus();
      },

      focusAdjacentRow(currentId, direction) {
        const ids = this.rowIds();
        const index = ids.indexOf(currentId);

        if (index === -1) {
          return;
        }

        const nextIndex = index + direction;

        if (nextIndex < 0 || nextIndex >= ids.length) {
          return;
        }

        this.focusRow(ids[nextIndex]);
      },

      moveRow(currentId, direction) {
        const ids = this.rowIds();
        const index = ids.indexOf(currentId);
        const targetIndex = index + direction;

        if (index === -1 || targetIndex < 0 || targetIndex >= ids.length) {
          return;
        }

        ids.splice(targetIndex, 0, ids.splice(index, 1)[0]);
        this.$wire.updateOrder(ids);

        this.$nextTick(() => this.focusRow(currentId));
      },
    }),
  );

  /**
   * The section picker: instant client-side search (matching name,
   * description, category, and keywords - see data-search on each card),
   * keyboard navigation across the currently-visible cards, and a small
   * "recently added" list persisted in localStorage per browser.
   */
  Alpine.data("inlaySectionPicker", (sectionsLookup = {}) => ({
    query: "",
    highlighted: null,
    recent: [],
    sectionsLookup,

    init() {
      this.recent = this.loadRecent().filter((slug) => this.sectionsLookup[slug]);
    },

    recentSections() {
      return this.recent.map((slug) => ({
        slug,
        ...this.sectionsLookup[slug],
      }));
    },

    matches(searchText) {
      return this.query === "" || searchText.includes(this.query.toLowerCase());
    },

    /**
     * Wraps the first match of the current query in <mark> so a card
     * visibly shows *why* it survived the filter, not just that it did.
     * text is always a section's own developer-supplied label (never
     * user/editor input), but it's still escaped before the <mark> tags are
     * spliced in, since x-html renders whatever this returns as real markup.
     */
    highlight(text) {
      if (this.query === "") {
        return this.escapeHtml(text);
      }

      const index = text.toLowerCase().indexOf(this.query.toLowerCase());

      if (index === -1) {
        return this.escapeHtml(text);
      }

      return (
        this.escapeHtml(text.slice(0, index)) +
        "<mark>" +
        this.escapeHtml(text.slice(index, index + this.query.length)) +
        "</mark>" +
        this.escapeHtml(text.slice(index + this.query.length))
      );
    },

    escapeHtml(text) {
      const div = document.createElement("div");
      div.textContent = text;

      return div.innerHTML;
    },

    categoryHasMatches(categoryEl) {
      return Array.from(categoryEl.querySelectorAll("[data-search]")).some(
        (el) => this.matches(el.dataset.search),
      );
    },

    hasAnyMatches() {
      // Deliberately $root, not $el: this is called from x-show on the
      // "no results" message itself, a leaf element with no descendants -
      // $el there would always search zero cards and report "no matches"
      // unconditionally. $root is the picker's own x-data element, so this
      // always searches the full set of cards regardless of which element
      // the calling expression lives on.
      return Array.from(this.$root.querySelectorAll("[data-search]")).some(
        (el) => this.matches(el.dataset.search),
      );
    },

    visibleCards() {
      return Array.from(
        this.$el.querySelectorAll('[data-test^="picker-section-"]'),
      ).filter((el) => el.offsetParent !== null);
    },

    highlightNext(direction) {
      const cards = this.visibleCards();

      if (cards.length === 0) {
        return;
      }

      const currentIndex = cards.indexOf(this.highlighted);
      const nextIndex =
        currentIndex === -1
          ? 0
          : (currentIndex + direction + cards.length) % cards.length;

      this.highlighted = cards[nextIndex];
      this.highlighted.scrollIntoView({ block: "nearest" });
    },

    chooseHighlighted() {
      this.highlighted?.click();
    },

    recordRecent(slug) {
      const recent = [slug, ...this.recent.filter((s) => s !== slug)].slice(
        0,
        5,
      );

      this.recent = recent;
      localStorage.setItem("inlay-recent-sections", JSON.stringify(recent));
    },

    loadRecent() {
      try {
        const stored = JSON.parse(
          localStorage.getItem("inlay-recent-sections") || "[]",
        );

        return Array.isArray(stored) ? stored : [];
      } catch {
        return [];
      }
    },
  }));

  /**
   * A named settings-inspector group's collapsed/expanded state, remembered
   * per group name across the whole admin (not per page or per section
   * instance - a developer's own naming convention, e.g. always calling
   * the advanced group "Advanced", is what makes that a sensible scope).
   */
  Alpine.data("inlayCollapsibleGroup", (name, collapsedByDefault) => ({
    collapsed: collapsedByDefault,

    init() {
      const stored = localStorage.getItem(this.storageKey());

      if (stored !== null) {
        this.collapsed = stored === "1";
      }
    },

    toggle() {
      this.collapsed = !this.collapsed;
      localStorage.setItem(this.storageKey(), this.collapsed ? "1" : "0");
    },

    storageKey() {
      return `inlay-group-collapsed:${name}`;
    },
  }));

  /**
   * The preview pane's selected viewport width (desktop/tablet/mobile) -
   * only ever resizes the iframe itself, never emulates a device.
   * Remembered across the whole admin.
   */
  Alpine.data("inlayViewportSelector", () => ({
    viewport: "desktop",

    init() {
      this.viewport =
        localStorage.getItem("inlay-preview-viewport") || "desktop";
    },

    select(viewport) {
      this.viewport = viewport;
      localStorage.setItem("inlay-preview-viewport", viewport);
    },
  }));

  /**
   * The "?" keyboard-shortcuts reference overlay. Global so it works from
   * anywhere in the editor, but ignores "?" while focus is inside a text
   * input/textarea/select or a contenteditable region - those need to type
   * a literal "?" far more often than an editor wants the overlay.
   */
  Alpine.data("inlayShortcutsHelp", () => ({
    open: false,

    onKeydown(event) {
      if (event.key !== "?" || this.isTypingTarget(event.target)) {
        return;
      }

      event.preventDefault();
      this.open = true;
    },

    isTypingTarget(target) {
      if (target.isContentEditable) {
        return true;
      }

      return ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName);
    },

    close() {
      this.open = false;
    },
  }));
});
