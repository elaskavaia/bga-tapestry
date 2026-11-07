import "./setup.js";
import { expect } from "chai";
import { TapAntiStock } from "../../modules/js/TapAntiStock.js";

describe("TapAntiStock.fork", () => {
  afterEach(() => document.querySelectorAll("#tiles_1, #tiles_2").forEach((div) => div.remove()));

  it("copies the settings without copying the container node", () => {
    const template = new TapAntiStock("territory").setAttribute("discard", "territory_deck");
    template.onSlide = () => {};
    const fork = template.fork().setAttribute("counter_id", "counter_terra_1");
    expect(fork).to.be.instanceOf(TapAntiStock);
    expect(fork.container_div).to.equal($("ebd-body"));
    expect(fork.discard).to.equal("territory_deck");
    expect(fork.onSlide).to.equal(template.onSlide);
    expect(template.counter_id).to.be.null;
  });

  it("keeps the subclass of the template", () => {
    class HandStock extends TapAntiStock {}
    expect(new HandStock("territory").fork()).to.be.instanceOf(HandStock);
  });

  it("binds each fork to its own player div, leaving the template on the game area", () => {
    document.body.insertAdjacentHTML("beforeend", "<div id='tiles_1'></div><div id='tiles_2'></div>");
    const template = new TapAntiStock("territory");
    const first = template.fork().bind(null, $("tiles_1"));
    const second = template.fork().bind(null, $("tiles_2"));
    expect(first.container_div.id).to.equal("tiles_1");
    expect(second.container_div.id).to.equal("tiles_2");
    expect(template.container_div).to.equal($("ebd-body"));
    expect($("tiles_1").classList.contains("territory_antistock")).to.be.true;
  });
});

describe("TapAntiStock.createDiv", () => {
  const clickAll = (onItemCreate) => {
    document.body.insertAdjacentHTML("beforeend", "<div id='click_stock'></div>");
    const stock = new TapAntiStock("territory").bind(null, $("click_stock"));
    stock.onItemCreate = onItemCreate;
    const clicked = [];
    stock.onClickOnItem = (event) => clicked.push(event.currentTarget.id);
    for (const item_id of [1, 2, 3]) stock.createDiv(7, item_id, "click_stock").click();
    $("click_stock").remove();
    return clicked;
  };

  it("makes every item clickable when the stock has no onItemCreate", () => {
    expect(clickAll(null)).to.deep.equal(["card_1", "card_2", "card_3"]);
  });

  it("makes every item clickable when onItemCreate returns nothing", () => {
    expect(clickAll(() => undefined)).to.deep.equal(["card_1", "card_2", "card_3"]);
  });

  it("leaves the click to onItemCreate when it returns a handle", () => {
    expect(clickAll(() => "handled")).to.be.empty;
  });
});
