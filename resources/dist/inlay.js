/*
 * Inlay admin interactivity - small, hand-authored Alpine.js data
 * components. No external libraries, no CDN dependency; Alpine itself
 * ships bundled with Livewire, which the admin already depends on.
 */
document.addEventListener("alpine:init", () => {
  /**
   * The contextual sidebar's whole client-side state: which pane is
   * showing (the component tree, a selected component's settings, or
   * version history), plus the component tree's drag-to-reorder and
   * keyboard navigation. All one Alpine component (rather than tree state
   * and pane-switching state living in separate x-data scopes) so
   * pane-switching methods can reach the tree's own $refs and focus
   * methods directly, with no cross-component event plumbing needed.
   *
   * Switching panes is deliberately client-only where nothing server-side
   * needs to change - going back to the tree, or the tree/settings split
   * itself, doesn't touch selectedSectionId, so it never round-trips.
   * Server-driven changes to *which* component is selected (adding,
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

      /**
       * Called both optimistically (a tree row's own click handler, the
       * instant a component with an already-known id is clicked - no need to
       * wait on the network round trip just to flip which pane is visible)
       * and via the section-selected event (addSection/duplicateSection,
       * whose new id doesn't exist until the server creates it).
       */
      showSettings(id) {
        this.lastSelectedId = id;
        this.view = "settings";
      },

      backToTree() {
        this.view = "tree";
        this.$nextTick(() => this.focusRow(this.lastSelectedId));
      },

      /**
       * Distinct from backToTree(): this responds to the server telling us
       * the previously-selected component is simply gone (deleted, or a
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
       * (see inlayComponentPicker) - this defers to that first, so Escape
       * closes the picker before it ever navigates the sidebar out of
       * settings, exactly as it would if they were unrelated.
       */
      onEscape() {
        if (this.$wire.showingPicker) {
          return;
        }

        if (this.view === "settings") {
          this.backToTree();
        } else if (this.view === "history") {
          this.$wire.toggleHistory();
        }
      },

      announcement() {
        if (this.view === "settings") {
          return "Editing component settings";
        }

        if (this.view === "history") {
          return "Showing version history";
        }

        return "Showing components";
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
   * The component picker: instant client-side search (matching name,
   * description, category, and keywords - see data-search on each card),
   * keyboard navigation across the currently-visible cards, and a small
   * "recently added" list persisted in localStorage per browser.
   */
  Alpine.data("inlayComponentPicker", (componentsLookup = {}) => ({
    query: "",
    highlighted: null,
    recent: [],
    componentsLookup,

    init() {
      this.recent = this.loadRecent().filter(
        (slug) => this.componentsLookup[slug],
      );
    },

    recentComponents() {
      return this.recent.map((slug) => ({
        slug,
        ...this.componentsLookup[slug],
      }));
    },

    matches(searchText) {
      return this.query === "" || searchText.includes(this.query.toLowerCase());
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
        this.$el.querySelectorAll('[data-test^="picker-card-"]'),
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
      localStorage.setItem("inlay-recent-components", JSON.stringify(recent));
    },

    loadRecent() {
      try {
        const stored = JSON.parse(
          localStorage.getItem("inlay-recent-components") || "[]",
        );

        return Array.isArray(stored) ? stored : [];
      } catch {
        return [];
      }
    },
  }));

  /**
   * A named settings-inspector group's collapsed/expanded state, remembered
   * per group name across the whole admin (not per page or per component
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
});
