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
    (initialView = "tree", initialSelectedId = null) => ({
      view: initialView,
      mobilePanel: "edit",
      lastSelectedId: initialSelectedId,
      dragging: null,
      width: 360,
      collapsed: false,
      resizing: false,
      skipNextScroll: false,
      previewScroll: 0,
      selectedBlockId: null,

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

        this.listenToPreview();
        this.selectedBlockId = this.$wire.selectedBlockId ?? null;
        window.addEventListener("block-selected", (event) => {
          this.selectedBlockId = event.detail.id ?? null;
          this.syncPreview(Boolean(this.selectedBlockId));
        });
        this.$watch("lastSelectedId", () => this.syncPreview(true));
        this.$watch("view", () => this.syncPreview(false));
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

          if (event.data?.type === "inlay:preview-ready") {
            this.syncPreview(false, this.previewScroll);
          }

          if (event.data?.type === "inlay:scrolled") {
            this.previewScroll = Number(event.data.y) || 0;
          }

          if (event.data?.type === "inlay:select-section") {
            const id = Number(event.data.id);

            this.skipNextScroll = true;
            this.showSettings(id);
            this.selectedBlockId = null;
            this.$wire.selectSection(id);
          }

          if (event.data?.type === "inlay:select-block") {
            const id = Number(event.data.id);

            this.skipNextScroll = true;
            this.showSettings(id);
            this.selectedBlockId = String(event.data.blockId);
            this.$wire.selectBlock(id, String(event.data.blockId));
          }
        });
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
            scroll: scroll && !this.skipNextScroll,
            restoreScroll,
            colour: getComputedStyle(this.$root).getPropertyValue("--inlay-focus").trim(),
          },
          window.location.origin,
        );

        this.skipNextScroll = false;
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
       * Called both optimistically (a tree row's own click handler, the
       * instant a section with an already-known id is clicked - no need to
       * wait on the network round trip just to flip which pane is visible)
       * and via the section-selected event (addSection/duplicateSection,
       * whose new id doesn't exist until the server creates it).
       */
      showSettings(id) {
        this.lastSelectedId = id;
        this.selectedBlockId = null;
        this.view = "settings";
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
          return "Editing section settings";
        }

        if (this.view === "page-settings") {
          return "Editing page settings";
        }

        if (this.view === "history") {
          return "Showing version history";
        }

        return "Showing sections";
      },

      dragStart(id) {
        this.dragging = id;
      },

      dragOver(event) {
        event.preventDefault();
      },

      drop(overId) {
        if (this.dragging === null || this.dragging === overId) {
          this.dragging = null;

          return;
        }

        this.reorder(this.dragging, overId);
        this.dragging = null;
      },

      reorder(fromId, toId) {
        const ids = this.rowIds();
        const fromIndex = ids.indexOf(fromId);
        const toIndex = ids.indexOf(toId);

        if (fromIndex === -1 || toIndex === -1) {
          return;
        }

        ids.splice(toIndex, 0, ids.splice(fromIndex, 1)[0]);
        this.$wire.updateOrder(ids);
      },

      rowIds() {
        return Array.from(this.$refs.list.children).map((el) =>
          parseInt(el.dataset.sectionId, 10),
        );
      },

      focusRow(id) {
        this.$refs.list.querySelector(`[data-section-id="${id}"]`)?.focus();
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
      this.highlighted.scrollIntoView({ section: "nearest" });
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
