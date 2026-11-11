import json
import re
import sys
from pathlib import Path

def check_ui(ui):
    errors = []
    slot = ui.get("reward_slot", {})
    if "collection_name" in slot:
        errors.append("reward_slot must inherit its collection from its parent")
    for count, columns in [(1, [4]), (3, [2, 4, 6]), (5, [0, 2, 4, 6, 8])]:
        reel = ui.get("reels_" + str(count), {})
        providers = [value for control in reel.get("controls", []) for value in control.values() if value.get("type") == "collection_panel" and value.get("collection_name") == "container_items"]
        if len(providers) != 1:
            errors.append(str(count) + "x reel must have one direct container_items collection provider")
            continue
        slots = [value for control in providers[0].get("controls", []) for key, value in control.items() if key.endswith("@soulwell.reward_slot")]
        indices = [value.get("collection_index") for value in slots]
        expected = {row * 9 + column for row in range(1, 6) for column in columns}
        if set(indices) != expected or len(indices) != len(expected):
            errors.append(str(count) + "x reel has an incorrect or duplicate slot mapping")
        if any(value.get("offset", [0, 0])[1] != 91 for value in slots if value.get("collection_index") in {27 + column for column in columns}):
            errors.append(str(count) + "x reel winning row is not aligned with the gold marker")
    for profile in ["classic_desktop", "classic_pocket", "data_desktop", "data_pocket"]:
        controls = ui.get(profile + "_dispatch", {}).get("controls", [])
        routed = [value for control in controls for key, value in control.items() if key == "original" or key.startswith("roll_")]
        original = next((control["original"] for control in controls if "original" in control), {})
        if original.get("type") != "panel" or original.get("visible") is not False or len(original.get("controls", [])) != 1:
            errors.append(profile + " must hide the original inventory through a dedicated wrapper")
        if len(routed) != 4:
            errors.append(profile + " must route to one original panel and three roll panels")
        for control in routed:
            properties = control.get("property_bag", {})
            if properties.get("#sw_container_title") != "$container_title":
                errors.append(profile + " must initialize the title binding property explicitly")
            for binding in control.get("bindings", []):
                if binding.get("binding_type") != "view":
                    continue
                reads = set(re.findall(r"#[A-Za-z_][A-Za-z_0-9]*", binding.get("source_property_name", "")))
                if binding.get("source_control_name") or not reads.issubset(properties):
                    errors.append(profile + " reads an uninitialized title property")
    confirm = ui.get("confirmation", {})
    confirm_controls = {key: value for control in confirm.get("controls", []) for key, value in control.items()}
    buttons = confirm_controls.get("buttons", {})
    indices = [value.get("collection_index") for control in buttons.get("controls", []) for value in control.values()]
    if buttons.get("type") != "collection_panel" or buttons.get("collection_name") != "form_buttons" or indices != [0, 1, 2, 3]:
        errors.append("confirmation must map three rolls and Prize List through the native form collection")
    routes = ui.get("form_dispatch", {}).get("controls", [])
    if len(routes) != 10 or not any(key.startswith("original@") for key in routes[0]):
        errors.append("form dispatcher must preserve other forms and route confirmation plus four list/detail pairs")
    for rarity in ["common", "rare", "epic", "legendary"]:
        grid = ui.get("prize_grid_" + rarity, {})
        if grid.get("type") != "collection_panel" or grid.get("collection_name") != "form_buttons":
            errors.append(rarity + " prize grid needs the native form collection parent")
        for i, control in enumerate(grid.get("controls", [])):
            key, value = next(iter(control.items()))
            if key.startswith("empty"):
                continue
            if value.get("collection_index") != i + 5 or value.get("offset") != [i % 2 * 138, i // 2 * 38]:
                errors.append(rarity + " prize indices or two-column positions are incorrect")
        screen = ui.get("prize_list_" + rarity, {})
        controls = {key: value for control in screen.get("controls", []) for key, value in control.items()}
        tabs = controls.get("tabs", {})
        actions = [value.get("collection_index") for control in tabs.get("controls", []) for value in control.values()]
        if actions != [0, 1, 2, 3, 4]:
            errors.append(rarity + " must expose four tabs and a Back action")
        scrolling = controls.get("list@common.scrolling_panel", {})
        if scrolling.get("$scrolling_content") != "soulwell.prize_grid_" + rarity:
            errors.append(rarity + " scrolling area points to the wrong prize grid")
        detail = ui.get("prize_detail_" + rarity, {})
        if detail.get("size") != [300, 166]:
            errors.append(rarity + " detail panel has an unexpected reference size")
    for text in ["§e§lSoulWell Prize List§r§r", "§e§lSoulWell Prize Detail§r§r"]:
        if text not in json.dumps(routes, ensure_ascii=False):
            errors.append("form routing marker is missing: " + text)
    return errors

if __name__ == "__main__":
    path = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(__file__).resolve().parents[1] / "resources/ClassicLargeChest/ui/soulwell_ui.json"
    errors = check_ui(json.loads(path.read_text()))
    if errors:
        print("\n".join(errors))
        sys.exit(1)
    print("Native collection contracts, roll slots, prize navigation, scrolling grids and isolated form routing passed")
