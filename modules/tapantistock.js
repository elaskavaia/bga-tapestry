/* Antistock */
define(["dojo", "dojo/_base/declare"], function (dojo, declare) {
  return declare("bgagame.tapantistock", null, {
    constructor: function (typeclass, mainclass) {
      this.container_div = $('ebd-body');
      this.mainclass = mainclass ?? "card";
      this.typeclass = typeclass ?? "type";
      this.jstpl_item = "<div id='${mainclass}_${id}' class='${mainclass} ${typeclass} ${typeclass}_${type} ${extra_classes}'></div>";
      this.extraClasses = "";
      this.selectionClass = "selected";
      this.unselectableClass = "unselectable";
      this.onItemCreate = null;
      this.onItemDelete = null;
      this.counter_id = null;
      this.from = null;
      this.discard = null;
    },

    bind: function (game, container_div, nomarkings) {
      this.container_div = container_div;
      if (nomarkings !== true) {
        dojo.addClass(container_div, "antistock");
        dojo.addClass(container_div, this.typeclass + "_antistock");
        dojo.addClass(container_div, this.mainclass + "_antistock");
        this.container_div.setAttribute(this.getSelectModeAttribute(), 2);
      }
      return this;
    },

    fork: function () {
      return dojo.clone(this);
    },

    setAttribute(key, value) {
      this[key] = value;
      return this;
    },

    getIdAttribute: function () {
      return "data-" + this.mainclass + "-id";
    },
    getTypeAttribute: function () {
      return "data-type-arg";
    },

    getSelectModeAttribute: function () {
      return "data-selectable";
    },

    getSelectedItems: function (parent) {
      const divs = this.getSelectedDivs(parent);
      let res = [];
      divs.forEach((node) => {
        res.push(this.getItemFromDiv(node));
      });
      return res;
    },

    getSelectedDivs: function (parent) {
      return $(parent ?? this.container_div).querySelectorAll(":scope > ." + this.selectionClass);
    },

    getChildrenDivs: function (parent) {
      return $(parent ?? this.container_div).querySelectorAll(":scope >*");
    },

    count: function (parent) {
      return $(parent ?? this.container_div).children.length;
    },

    getItemNumber: function () {
      return this.count();
    },

    getItemFromDiv: function (div) {
      div = $(div);
      if (!div) return null;
      return {
        id: div.getAttribute(this.getIdAttribute()),
        type: div.getAttribute(this.getTypeAttribute()),
      };
    },

    getIdFromDiv: function (div) {
      div = $(div);
      if (!div) return null;
      return div.getAttribute(this.getIdAttribute());
    },

    getTypeFromDiv: function (div) {
      div = $(div);
      if (!div) return null;
      return div.getAttribute(this.getTypeAttribute());
    },

    getItemById: function (item_id, parent) {
      if (!$(parent)) parent = this.container_div;
      const div = this.findDivById(item_id, parent);
      return this.getItemFromDiv(div);
    },

    findDivById: function (item_id, parent) {
      if (!$(parent)) parent = this.container_div;
      const idattr = this.getIdAttribute();
      return parent.querySelector(`*[${idattr}='${item_id}']`);
    },

    findDivByType: function (type, parent) {
      if (!$(parent)) parent = this.container_div;
      const idattr = this.getTypeAttribute();
      return parent.querySelector(`.${this.mainclass}.${this.typeclass}[${idattr}='${type}']`);
    },

    getItemDivId: function (item_id, parent) {
      const div = this.findDivById(item_id, parent);
      return div?.id;
    },

    findOrCreateDiv: function (item_type, item_id, location, extra) {
      let div = this.findDivById(item_id);
      if (!div) div = this.createDiv(item_type, item_id, location, extra);
      return div;
    },

    createDiv: function (item_type, item_id, location, extra) {
      if (!location || !$(location)) location = this.from ?? this.discard ?? this.container_div;
      if (!$(location)) location = document;

      var item_html = dojo.trim(
        dojo.string.substitute(this.jstpl_item, {
          id: item_id,
          typeclass: this.typeclass,
          type: item_type,
          mainclass: this.mainclass,
          extra_classes: this.extraClasses,
        })
      );
      const item_div = dojo.place(item_html, location);
      dojo.attr(item_div, this.getIdAttribute(), item_id);
      dojo.attr(item_div, this.getTypeAttribute(), item_type);

      if (this.onItemCreate) {
        if (this.onclickRemoveId) dojo.disconnect(this.onItemCreateConnectId);
        this.onclickRemoveId = this.onItemCreate(item_div, item_type, item_div.id, item_id, extra);
      }
      if (!this.onclickRemoveId) this.onclickRemoveId = dojo.connect(item_div, "onclick", this, "onClickOnItem");
      return item_div;
    },

    isSelected: function (id) {
      const div = this.findDivById(id);
      if (!div) return false;
      return dojo.hasClass(div, this.selectionClass);
    },

    // Select item with specified id (raw method)
    selectItem: function (id, selected) {
      if (selected === undefined) selected = true;
      console.log("Selected item " + id);
      this.selectDiv(this.findDivById(id), selected);
    },

    unselectItem: function (id) {
      this.selectItem(id, false);
    },

    selectDiv: function (div, selected) {
      if (!div) return;
      if (selected) {
        dojo.addClass(div, this.selectionClass);
      } else {
        dojo.removeClass(div, this.selectionClass);
      }
    },

    selectAll: function (selected) {
      if (selected === undefined) selected = true;
      const count1 = this.getSelectedDivs();
      this.getChildrenDivs().forEach((node) => this.selectDiv(node, selected));
      const count2 = this.getSelectedDivs();
      if (count1 != count2) this.onChangeSelection(this.container_div.id);
    },
    unselectAll: function () {
      this.selectAll(false);
    },

    getSelectMode: function (div) {
      div = $(div);
      if (!div) div = this.container_div;
      const attr = this.getSelectModeAttribute();
      let selectable = div.getAttribute(attr);
      if (selectable === undefined) selectable = 2;
      else selectable = parseInt(selectable);
    },

    onClickOnItem: function (event) {
      console.log("onClickOnItem "+this.container_div.id);
      event.stopPropagation();

      let selectable = this.getSelectMode();

      if (selectable !== 0) {
        var item_id = this.getIdFromDiv(event.currentTarget);

        if (this.isSelected(item_id)) {
          this.selectItem(item_id, false);
        } else {
          if (selectable === 1) {
            this.selectAll(false);
          }
          this.selectItem(item_id, true);
        }
        this.onChangeSelection(this.container_div.id, item_id);
      }
    },

    // Called every time there is a change in the selection
    onChangeSelection: function (control_name, item_id) {
      // (to be connected to client methods)
    },

    setSelectionMode: function (mode) {
      let selectable = this.getSelectMode();
      if (mode != selectable) {
        // ..so we do not unselect all when there is nothing to do
        this.selectAll(false);
        const attr = this.getSelectModeAttribute();
        this.container_div.setAttribute(attr, mode);

        // Adjust cursor display when the stock items are not selectable
        if (mode == 0) {
          this.getChildrenDivs().forEach((node) => node.classList.add(this.unselectableClass));
        } else {
          this.getChildrenDivs().forEach((node) => node.classList.remove(this.unselectableClass));
        }
      }
      return this;
    },

    addToStockWithId: function (type, id, from, extra) {
      const found = this.findDivById(id, document);
      if (found) {
        this.slideIn(found);
        return;
      }
      const div = this.createDiv(type, id, from, extra);
      if ($(from) !== this.container_div) {
        this.slideIn(div);
      }
    },

    slide: function (mobile, new_parent) {
      // it has to be bound to something, this is just placement
      dojo.place(mobile, new_parent);
    },

    slideIn: function (mobile) {
      this.slide(mobile, this.container_div);
      this.updateCounter();
    },

    slideInById: function (item_id) {
      const mobile = this.findDivById(item_id);
      if (mobile) this.slide(mobile, this.container_div);
    },

    updateCounter: function (counter_name) {
      if (!counter_name) counter_name = this.counter_id;
      if (!counter_name) return;
      if (!$(counter_name)) return;
      $(counter_name).innerHTML = this.count();
    },

    // Remove item of specific type from the board. Note: it may not be actually in this container
    // If "to" is specified: move item to this position before destroying it
    removeFromStockById: function (id, to) {
      const div = this.findDivById(id);
      if (div) {
        this.removeDiv(div, to);
        this.updateCounter();
        return true;
      }

      return false;
    },

    removeDiv: function (div, to) {
      div = $(div);
      if (!div) return;

      // Item deletion hook (allow user to perform some additional item delete operation)
      if (this.onItemDelete) {
        const item = this.getItemFromDiv(div);
        this.onItemDelete(div.id, item.type, item.id);
      }

      // Trigger immediately the disappearance of corresponding item

      dojo.addClass(div, "to_be_destroyed");

      if (to !== undefined) {
        this.slide(div, to);
      } else {
        this.fadeOutAndDestroy(div);
      }
    },

    fadeOutAndDestroy: function (div) {
      div = $(div);
      if (!div) return;
      dojo.fadeOut({ node: div, onEnd: () => dojo.destroy(div) }).play();
    },
  });
});
