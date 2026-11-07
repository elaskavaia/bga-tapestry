/** Stubs the BGA framework globals; import it before any game module. */
import { JSDOM } from "jsdom";

const dom = new JSDOM("<!doctype html><html><body><div id='ebd-body'></div><div id='generalactions'></div></body></html>");
const document = dom.window.document;
const node = (id) => (typeof id === "string" ? document.getElementById(id) : id);

Object.assign(globalThis, {
  dojo: {
    addClass: (id, classes) => node(id).classList.add(...classes.split(" ")),
    attr: (id, name, value) => node(id).setAttribute(name, value),
    // as dojo 1.x lang.clone: functions by reference, objects rebuilt with their constructor
    clone(src) {
      if (!src || typeof src != "object") return src;
      if (src.nodeType) return src.cloneNode(true);
      const copy = new src.constructor();
      for (const name in src) copy[name] = this.clone(src[name]);
      return copy;
    },
    create: (tag, attributes) => Object.assign(document.createElement(tag), { className: attributes?.class ?? "" }),
    destroy: (id) => node(id)?.remove(),
    empty: (id) => node(id).replaceChildren(),
    hitch:
      (scope, method) =>
      (...args) =>
        scope[method](...args),
    place: (mobile, parent) => node(parent).appendChild(node(mobile)),
    setStyle: (id, property, value) => node(id).style.setProperty(property, value),
    style: (id, property, value) => node(id).style.setProperty(property, value)
  },
  window: dom.window,
  document,
  HTMLElement: dom.window.HTMLElement,
  Element: dom.window.Element,
  $: node,
  _: (text) => text,
  __: (_domain, text) => text,
  // Game.js loads ebg/stock with a top-level await
  importDojoLibs: async (names) => names.map(() => class {})
});

/** Minimal injected bga object, gameui holds the live gamedatas */
export function makeBga(gamedatas, currentPlayerId) {
  return {
    gameui: { gamedatas, clienttranslate_string: (text) => text },
    statusBar: {
      addActionButton: (label, callback, params = {}) => {
        const button = document.createElement("button");
        if (params.id) button.id = params.id;
        button.className = "action-button bgabutton";
        button.dataset.color = params.color ?? "primary";
        button.innerHTML = label;
        button.addEventListener("click", callback);
        (params.destination ?? document.getElementById("generalactions")).appendChild(button);
        return button;
      }
    },
    players: {
      getCurrentPlayerId: () => currentPlayerId,
      isCurrentPlayerSpectator: () => !gamedatas.players[currentPlayerId]
    }
  };
}
