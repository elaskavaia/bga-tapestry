import "./setup.js";
import { expect } from "chai";
import { getGameAreaHtml } from "../../modules/js/TapLayout.js";

describe("TapLayout.getGameAreaHtml", () => {
  const players = [10, 20, 30, 40, 2].map((id) => ({ id, name: "p" + id, color: "ff0000" }));
  const area = document.createElement("div");
  area.innerHTML = getGameAreaHtml(players);
  const ids = (selector) => [...area.querySelectorAll(selector)].map((element) => element.id);

  it("adds one player area per player, in the given order", () => {
    expect(ids("#playerArea_wrapper > .playerArea")).to.deep.equal(players.map((player) => "playerArea_" + player.id));
  });

  it("gives every player 5 tapestry slots and 24 income track spaces", () => {
    for (const player of players) {
      expect(area.querySelectorAll(`#income_mat_${player.id} .tapestry_slot`)).to.have.length(5);
      expect(area.querySelectorAll(`#income_mat_${player.id} .income_track_space`)).to.have.length(24);
    }
  });

  it("puts 33 tech cards in limbo and hides only landmark slot 19", () => {
    expect(area.querySelectorAll("#limbo > .tech_card")).to.have.length(33);
    expect(area.querySelectorAll(".landmark_slot")).to.have.length(19);
    expect(ids(".landmark_slot.hidden")).to.deep.equal(["landmark_mat_slot19"]);
  });

  it("keeps whitespace between inline siblings, as the tpl did", () => {
    const coins = area.querySelector("#counter_resource_1_10");
    expect(coins.nextSibling.nodeType).to.equal(3);
  });
});
