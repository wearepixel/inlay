/*
 * Inlay admin interactivity - small, hand-authored Alpine.js data
 * components. No external libraries, no CDN dependency; Alpine itself
 * ships bundled with Livewire, which the admin already depends on.
 */
document.addEventListener("alpine:init", () => {
  /**
   * The component tree: drag-to-reorder (native HTML5 drag events) plus a
   * keyboard-operable equivalent - arrow keys move focus between rows,
   * Alt+Arrow reorders the focused row exactly like a drag would, Enter
   * selects it, Delete removes it. Reordering always goes through the same
   * $wire.updateOrder() call the drag handler uses, so the two are
   * genuinely equivalent, not a separate parallel mechanism.
   */
  Alpine.data("inlaySortableTree", () => ({
    dragging: null,

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
  }));

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
