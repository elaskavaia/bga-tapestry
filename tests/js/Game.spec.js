import { makeBga } from "./setup.js";
import { expect } from "chai";
import { Game } from "../../modules/js/Game.js";

const ME = 10;
const OTHER = 20;

function makeGame() {
  const gamedatas = {
    players: {
      [ME]: { id: ME, name: "Alice", color: "ff0000" },
      [OTHER]: { id: OTHER, name: "Bob", color: "0000ff" }
    }
  };
  return new Game(makeBga(gamedatas, ME));
}

describe("Game.bgaFormatText", () => {
  it("shows log_others to a player other than the actor", () => {
    const { log } = makeGame().bgaFormatText("${You} keep a card", { player_id: OTHER, log_others: "${player_name} keeps a card" });
    expect(log).to.equal("${player_name} keeps a card");
  });

  it("keeps the own log for the actor", () => {
    const { log } = makeGame().bgaFormatText("${You} keep a card", { player_id: ME, log_others: "${player_name} keeps a card" });
    expect(log).to.equal("${You} keep a card");
  });

  it("replaces ${You} with the colored current player name, once", () => {
    const args = { player_id: ME };
    const game = makeGame();
    game.bgaFormatText("${You} advance", args);
    expect(args.You).to.contain("ff0000").and.contain("You");

    args.You = "unchanged";
    game.bgaFormatText("${You} advance", args);
    expect(args.You).to.equal("unchanged");
  });
});
