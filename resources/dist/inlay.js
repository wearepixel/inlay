/*
 * Inlay admin interactivity - a small, hand-authored Alpine.js data
 * component for drag-to-reorder using native HTML5 drag events. No
 * external sortable library, no CDN dependency; Alpine itself ships
 * bundled with Livewire, which the admin already depends on.
 */
document.addEventListener("alpine:init", () => {
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

      const ids = Array.from(this.$refs.list.children).map((el) =>
        parseInt(el.dataset.sectionId, 10),
      );

      const fromIndex = ids.indexOf(this.dragging);
      const toIndex = ids.indexOf(overId);

      if (fromIndex === -1 || toIndex === -1) {
        this.dragging = null;

        return;
      }

      ids.splice(toIndex, 0, ids.splice(fromIndex, 1)[0]);
      this.dragging = null;

      this.$wire.updateOrder(ids);
    },
  }));
});
