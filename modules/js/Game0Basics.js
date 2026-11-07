export function getIntPart(word, i) {
  return parseInt(getPart(word, i));
}

export function getPart(word, i) {
  var arr = word.split("_");
  if (i < 0) i = arr.length + i;
  if (i >= arr.length) return "";
  return arr[i];
}

/** Non game specific base: proxies to gameui, which Game no longer extends */
export class Game0Basics {
  constructor(bga) {
    this.bga = bga;
    this.hookedGameui = new Set();
  }

  // must stay the framework's object, it updates gamestate on it and replaces it on cancel
  get gamedatas() {
    return this.bga.gameui.gamedatas;
  }
  set gamedatas(value) {
    this.bga.gameui.gamedatas = value;
  }
  get notifqueue() {
    return this.bga.gameui.notifqueue;
  }
  get tooltips() {
    return this.bga.gameui.tooltips;
  }
  get table_id() {
    return this.bga.gameui.table_id;
  }
  get metasiteurl() {
    return this.bga.gameui.metasiteurl;
  }

  /**
   * Framework calls these on gameui only. Each runs the gameui original, then the Game method.
   * Guarded because the framework re-runs setup after an undo restore.
   */
  hookGameui(names) {
    const gameui = this.bga.gameui;
    for (const name of names) {
      if (this.hookedGameui.has(name)) continue;
      this.hookedGameui.add(name);
      const original = gameui[name].bind(gameui);
      gameui[name] = (...args) => {
        const result = original(...args);
        this[name](...args);
        return result;
      };
    }
    // history logs are added before setup
    if (names.includes("addMoveToLog")) {
      for (const log_id in gameui.log_to_move_id ?? {}) this.addMoveToLog(log_id, gameui.log_to_move_id[log_id]);
    }
  }

  // gameui resolves a string handler on itself, which is not the Game any more
  resolveHandler(handler) {
    if (typeof handler != "string") return handler;
    if (typeof this[handler] != "function") throw new Error("No handler " + handler);
    return (...args) => this[handler](...args);
  }
  connect(node, event, handler) {
    return this.bga.gameui.connect(node, event, this.resolveHandler(handler));
  }
  connectClass(className, event, handler) {
    return this.bga.gameui.connectClass(className, event, this.resolveHandler(handler));
  }

  getPlayerId() {
    return this.bga.players.getCurrentPlayerId();
  }

  isReadOnly() {
    return this.bga.players.isCurrentPlayerSpectator() || typeof g_replayFrom != "undefined" || g_archive_mode;
  }

  isInstantaneousMode() {
    return !this.bga.gameui.bgaAnimationsActive();
  }
}

for (const name of [
  "addTooltip",
  "addTooltipHtml",
  "clienttranslate_string",
  "confirmationDialog",
  "disablePlayerPanel",
  "disconnect",
  "fadeOutAndDestroy",
  "format_block",
  "format_string",
  "format_string_recursive",
  "placeOnObject",
  "removeTooltip",
  "slideTemporaryObject",
  "slideToObjectAndDestroy"
]) {
  Game0Basics.prototype[name] = function (...args) {
    return this.bga.gameui[name](...args);
  };
}
