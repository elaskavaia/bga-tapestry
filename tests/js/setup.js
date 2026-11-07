/** Stubs the BGA framework globals; import it before any game module. */
import { JSDOM } from "jsdom";

const dom = new JSDOM("<!doctype html><html><body><div id='ebd-body'></div></body></html>");

Object.assign(globalThis, {
  window: dom.window,
  document: dom.window.document,
  HTMLElement: dom.window.HTMLElement,
  Element: dom.window.Element,
  $: (id) => (typeof id === "string" ? dom.window.document.getElementById(id) : id),
  _: (text) => text,
  __: (_domain, text) => text,
  // Game.js loads ebg/stock with a top-level await
  importDojoLibs: async (names) => names.map(() => class {})
});

/** Minimal injected bga object, gameui holds the live gamedatas */
export function makeBga(gamedatas, currentPlayerId) {
  return {
    gameui: { gamedatas },
    players: {
      getCurrentPlayerId: () => currentPlayerId,
      isCurrentPlayerSpectator: () => !gamedatas.players[currentPlayerId]
    }
  };
}
