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
        routed = [value for control in controls for key, value in control.items() if key.startswith("original@") or key.startswith("roll_")]
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
    return errors

if __name__ == "__main__":
    path = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(__file__).resolve().parents[1] / "resources/ClassicLargeChest/ui/soulwell_ui.json"
    errors = check_ui(json.loads(path.read_text()))
    if errors:
        print("\n".join(errors))
        sys.exit(1)
    print("Native collection parent/index contracts, winning slots and initialized title properties passed")
