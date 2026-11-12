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
        if len(routed) != 7:
            errors.append(profile + " must route to one original panel and three roll panels for each language")
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
    if len(routes) != 19 or "original" not in routes[0]:
        errors.append("form dispatcher must preserve other forms and route confirmation plus four list/detail pairs in both languages")
    original_form = routes[0].get("original", {}) if routes else {}
    inherited = original_form.get("controls", [])
    if original_form.get("type") != "panel" or original_form.get("visible") is not False or len(inherited) != 1 or not any(key.startswith("content@") for key in inherited[0]):
        errors.append("the existing form dispatcher must be inside a separate hidden wrapper, inherited children can override their own visibility")
    if original_form.get("property_bag", {}).get("#sw_form_title") != "":
        errors.append("the existing form wrapper must initialize its own title property")
    bindings = original_form.get("bindings", [])
    if not any(binding.get("binding_name") == "#title_text" and binding.get("binding_name_override") == "#sw_form_title" for binding in bindings):
        errors.append("the form wrapper must bind the live form title to its own property")
    close = ui.get("close@common.button", {})
    if close.get("anchor_from") != "top_right" or close.get("anchor_to") != "top_right":
        errors.append("the shared close button must anchor to the panel top right")
    close_width, close_height = close.get("size", [0, 0])
    close_x, close_y = close.get("offset", [0, 0])
    if close_width <= 0 or close_height <= 0 or close_x > -5 or close_y < 5 or close_y + close_height > 20:
        errors.append("close button must fit inside the shortest header with at least five pixels of top and right inset")
    for control in close.get("controls", []):
        for state in control.values():
            x, y = state.get("offset", [0, 0])
            width, height = state.get("size", [0, 0])
            if x < 0 or y < 0 or x + width > close_width or y + height > close_height:
                errors.append("close button artwork extends outside its click area")
    for width, height in [(256, 196), (300, 176), (300, 166), (256, 168)]:
        left = width - close_width + close_x
        if left < 5 or left + close_width > width - 5 or close_y + close_height > height - 5:
            errors.append("close button leaves the panel border at " + str((width, height)))
    tab = ui.get("prize_tab@common.button", {})
    locked = next((value for control in tab.get("controls", []) for key, value in control.items() if key == "locked@soulwell.prize_tab_state"), {})
    if tab.get("locked_control") != "locked" or locked.get("$sw_tab_texture") != "$sw_tab_active":
        errors.append("disabled rarity tabs must retain their selected artwork through a locked control")
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
        for i, control in enumerate(tabs.get("controls", [])[:4]):
            key, value = next(iter(control.items()))
            current = key == "tab_" + rarity + "@soulwell.prize_tab"
            if value.get("enabled") is not (not current) or value.get("$focus_enabled") is not (not current):
                errors.append(rarity + " must disable clicks and focus only on its current tab")
        scrolling = controls.get("list@common.scrolling_panel", {})
        if scrolling.get("$scrolling_content") != "soulwell.prize_grid_" + rarity:
            errors.append(rarity + " scrolling area points to the wrong prize grid")
        detail = ui.get("prize_detail_" + rarity, {})
        if detail.get("size") != [300, 166]:
            errors.append(rarity + " detail panel has an unexpected reference size")
    localized = [control for route in routes[1:] for key, control in route.items() if "_ind@" in key]
    english = [control for route in routes[1:] for key, control in route.items() if "_ind@" not in key]
    if len(localized) != 9 or len(english) != 9:
        errors.append("each form language needs exactly nine routes")
    for controls, guard in [(english, "and (not (not ((#title_text - '§0§0§r') = #title_text)))"), (localized, "and (not ((#title_text - '§0§0§r') = #title_text))")]:
        if any(guard not in control.get("bindings", [{}])[-1].get("source_property_name", "") for control in controls):
            errors.append("English and Indonesian form routes must use opposite language guards")
    for rarity in ["common", "rare", "epic", "legendary"]:
        controls = {key: value for control in ui.get("prize_list_" + rarity + "_ind", {}).get("controls", []) for key, value in control.items()}
        if controls.get("list@common.scrolling_panel", {}).get("$scrolling_content") != "soulwell.prize_grid_" + rarity + "_ind":
            errors.append(rarity + " Indonesian list must use its own localized grid")
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
    print("Native collection contracts, prize navigation, separate form wrapper and inset close button bounds passed")
