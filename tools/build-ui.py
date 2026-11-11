import json
import struct
import zlib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "resources" / "ClassicLargeChest"
TEXTURES = ROOT / "textures" / "ui" / "soulwell"
TITLE = "§e§lSoul Well Confirm§r§r"
PANELS = {1: ([4], [116]), 3: ([2, 4, 6], [68, 116, 164]), 5: ([0, 2, 4, 6, 8], [28, 72, 116, 160, 204])}

def write_json(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("{\n" + ",\n".join("    " + json.dumps(k, ensure_ascii=False) + ": " + json.dumps(v, ensure_ascii=False) for k, v in data.items()) + "\n}\n")

def image(width, height, color=(0, 0, 0, 0)):
    return [[tuple(color) for x in range(width)] for y in range(height)]

def rect(pixels, box, color):
    x0, y0, x1, y1 = box
    for y in range(max(0, y0), min(len(pixels), y1)):
        for x in range(max(0, x0), min(len(pixels[0]), x1)):
            pixels[y][x] = (*color, 255) if len(color) == 3 else color

def png(name, pixels, nine_slice=None):
    height, width = len(pixels), len(pixels[0])
    raw = b"".join(b"\x00" + bytes(channel for pixel in row for channel in pixel) for row in pixels)
    def chunk(kind, data):
        return struct.pack(">I", len(data)) + kind + data + struct.pack(">I", zlib.crc32(kind + data) & 0xffffffff)
    data = b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 6, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(raw, 9)) + chunk(b"IEND", b"")
    TEXTURES.mkdir(parents=True, exist_ok=True)
    (TEXTURES / (name + ".png")).write_bytes(data)
    if nine_slice is not None:
        write_json(TEXTURES / (name + ".json"), {"nineslice_size": nine_slice, "base_size": [width, height]})

def textures():
    p = image(256, 168)
    rect(p, (2, 2, 256, 168), (3, 8, 17, 210))
    rect(p, (0, 0, 254, 166), (6, 16, 28))
    rect(p, (1, 1, 253, 165), (20, 59, 72))
    rect(p, (2, 2, 252, 164), (52, 105, 117))
    rect(p, (3, 3, 251, 163), (12, 31, 45))
    rect(p, (4, 4, 250, 162), (21, 25, 46))
    rect(p, (4, 4, 250, 19), (29, 74, 90))
    rect(p, (4, 4, 250, 5), (49, 100, 112))
    rect(p, (4, 19, 250, 20), (9, 26, 37))
    rect(p, (4, 20, 250, 41), (10, 27, 39))
    rect(p, (4, 41, 250, 42), (4, 14, 26))
    png("panel", p)
    for name, fill, edge, top in [("button", (31, 61, 77), (66, 101, 113), (79, 116, 126)), ("button_hover", (26, 105, 119), (117, 218, 209), (158, 239, 222)), ("button_pressed", (18, 75, 92), (225, 197, 99), (255, 226, 133))]:
        p = image(16, 16)
        rect(p, (0, 0, 16, 16), (5, 14, 25))
        rect(p, (1, 1, 15, 15), edge)
        rect(p, (2, 2, 14, 14), fill)
        rect(p, (2, 2, 14, 3), top)
        rect(p, (2, 13, 14, 14), (15, 35, 49))
        png(name, p, 3)
    p = image(24, 24)
    rect(p, (0, 0, 24, 24), (9, 26, 38))
    rect(p, (1, 0, 23, 24), (32, 69, 80))
    rect(p, (2, 1, 22, 23), (20, 47, 59))
    rect(p, (3, 1, 21, 2), (40, 82, 90))
    png("slot", p)
    p = image(12, 12)
    rect(p, (1, 1, 11, 11), (93, 68, 26, 180))
    rect(p, (2, 2, 10, 10), (48, 54, 42, 215))
    png("selection_fill", p, 3)
    p = image(12, 12)
    rect(p, (1, 1, 11, 11), (136, 93, 29))
    rect(p, (2, 2, 10, 10), (251, 213, 103))
    rect(p, (3, 3, 9, 9), (180, 136, 49))
    rect(p, (4, 4, 8, 8), (0, 0, 0, 0))
    for x, y in [(0, 0), (8, 0), (0, 8), (8, 8)]:
        rect(p, (x, y, x + 4, y + 4), (255, 227, 136))
        rect(p, (x + 1, y + 1, x + 3, y + 3), (135, 94, 29))
    png("selection", p, 5)
    for name, flip in [("pointer_left", False), ("pointer_right", True)]:
        p = image(7, 11)
        for y in range(11):
            x = min(y, 10 - y)
            for dx in range(2):
                px = 5 - x + dx if flip else x + dx
                rect(p, (px, y, px + 1, y + 1), (255, 214, 101))
        png(name, p)
    for name, fill, cross in [("close", (26, 61, 78), (167, 205, 210)), ("close_hover", (32, 105, 117), (255, 226, 133)), ("close_pressed", (17, 64, 79), (255, 226, 133))]:
        p = image(14, 14)
        rect(p, (0, 0, 14, 14), (8, 24, 37))
        rect(p, (1, 1, 13, 13), (52, 94, 106))
        rect(p, (2, 2, 12, 12), fill)
        for i in range(6):
            rect(p, (4 + i, 4 + i, 5 + i, 5 + i), cross)
            rect(p, (9 - i, 4 + i, 10 - i, 5 + i), cross)
        png(name, p)
    p = image(12, 12)
    for box in [(1, 2, 5, 3), (0, 3, 2, 6), (4, 3, 6, 6), (1, 6, 5, 7), (5, 5, 11, 7), (8, 7, 10, 9), (10, 7, 12, 8)]:
        rect(p, box, (241, 211, 123))
    png("key", p)

def picture(texture, size, offset=(0, 0), layer=1, **extra):
    return {"type": "image", "texture": "textures/ui/soulwell/" + texture, "size": list(size), "anchor_from": "top_left", "anchor_to": "top_left", "offset": list(offset), "layer": layer, **extra}

def label(text, size, offset, **extra):
    return {"type": "label", "text": text, "size": list(size), "offset": list(offset), "anchor_from": "top_left", "anchor_to": "top_left", "color": [0.91, 0.95, 0.96], "shadow": True, "layer": 4, **extra}

def gate(expression):
    return [{"binding_name": "#title_text"}, {"binding_type": "view", "source_property_name": expression, "target_property_name": "#visible"}]

def controls():
    is_soulwell = "(not ((#title_text - '§e§lSoul Well Confirm§r') = #title_text))"
    ui = {"namespace": "soulwell"}
    ui["form_dispatch"] = {"type": "panel", "size": [0, 0], "controls": [{"original@server_form.long_form": {"visible": False, "bindings": gate("(not " + is_soulwell + ")")}}, {"soulwell@soulwell.confirmation": {"visible": False, "bindings": gate(is_soulwell)}}]}
    ui["frame"] = picture("panel", (256, 168), layer=0)
    ui["close@common.button"] = {"size": [20, 18], "anchor_from": "top_right", "anchor_to": "top_right", "offset": [-4, 1], "layer": 40, "$pressed_button_name": "button.menu_exit", "controls": [{"default": picture("close", (14, 14), (3, 2))}, {"hover": picture("close_hover", (14, 14), (3, 2))}, {"pressed": picture("close_pressed", (14, 14), (3, 2))}]}
    ui["confirmation"] = {"type": "panel", "size": [256, 168], "layer": 5, "controls": [{"frame@soulwell.frame": {}}, {"title": label("§lSOUL WELL", (210, 12), (21, 7), color=[1, 0.86, 0.43], text_alignment="center")}, {"subtitle": label("Choose your rolls", (238, 12), (9, 24), shadow=False, text_alignment="center", color=[0.7, 0.79, 0.83])}, {"greeting": label("#title_text", (128, 12), (12, 48), bindings=[{"binding_name": "#title_text"}, {"binding_type": "view", "source_property_name": "(#title_text - " + json.dumps(TITLE, ensure_ascii=False).replace('"', "'") + ")", "target_property_name": "#text"}])}, {"balance": label("#form_text", (100, 12), (134, 48), text_alignment="right", bindings=[{"binding_name": "#form_text"}])}, {"balance_icon": picture("key", (10, 10), (236, 48), layer=5)}, {"button_1@soulwell.roll_button": {"offset": [12, 68], "collection_index": 0}}, {"button_3@soulwell.roll_button": {"offset": [12, 100], "collection_index": 1}}, {"button_5@soulwell.roll_button": {"offset": [12, 132], "collection_index": 2}}, {"close@soulwell.close": {}}]}
    button_label = label("#form_button_text", (220, 14), (6, 8), text_alignment="center", bindings=[{"binding_name": "#form_button_text", "binding_type": "collection", "binding_collection_name": "form_buttons"}])
    ui["roll_button@common.button"] = {"size": [232, 28], "anchor_from": "top_left", "anchor_to": "top_left", "collection_name": "form_buttons", "$pressed_button_name": "button.form_button_click", "bindings": [{"binding_type": "collection_details", "binding_collection_name": "form_buttons"}], "controls": [{"default@soulwell.roll_button_state": {"$sw_button_texture": "textures/ui/soulwell/button"}}, {"hover@soulwell.roll_button_state": {"$sw_button_texture": "textures/ui/soulwell/button_hover", "$sw_key_visible": True}}, {"pressed@soulwell.roll_button_state": {"$sw_button_texture": "textures/ui/soulwell/button_pressed", "$sw_key_visible": True}}]}
    ui["roll_button_state"] = {"type": "panel", "$sw_key_visible|default": False, "controls": [{"background": {**picture("button", ("100%", "100%")), "texture": "$sw_button_texture"}}, {"text": button_label}, {"key": picture("key", (12, 12), (213, 8), layer=5, visible="$sw_key_visible")}]}
    ui["roll_button_control@common.button"] = ui.pop("roll_button@common.button")
    ui["roll_button"] = {"type": "panel", "size": [232, 32], "controls": [{"button@soulwell.roll_button_control": {}}]}
    ui["roll_buttons"] = {"type": "stack_panel", "size": [232, 96], "offset": [12, 68], "anchor_from": "top_left", "anchor_to": "top_left", "orientation": "vertical", "collection_name": "form_buttons", "factory": {"name": "buttons", "control_ids": {"button": "soulwell.roll_button"}}, "bindings": [{"binding_name": "#form_button_contents", "binding_name_override": "#collection_length", "binding_condition": "visible"}]}
    ui["confirmation"]["controls"] = [entry for entry in ui["confirmation"]["controls"] if not next(iter(entry)).startswith("button_")] + [{"roll_buttons@soulwell.roll_buttons": {}}]
    ui["confirmation"]["controls"][3]["greeting"]["text"] = "#text"
    ui["slot_background"] = picture("slot", (24, 24))
    ui["reward_slot"] = {"type": "panel", "size": [24, 24], "$item_collection_name": "container_items", "$stack_count_required": True, "anchor_from": "top_left", "anchor_to": "top_left", "bindings": [{"binding_type": "collection_details", "binding_collection_name": "container_items"}], "controls": [{"background@soulwell.slot_background": {}}, {"item@common.item_renderer": {"size": [20, 20], "layer": 7}}, {"count@common.stack_count_label": {"offset": [-2, -1], "layer": 27}}]}
    for count, (columns, positions) in PANELS.items():
        reel = []
        for col, x in zip(columns, positions):
            for row in range(1, 6):
                index = row * 9 + col
                reel.append({"slot_" + str(index) + "@soulwell.reward_slot": {"collection_index": index, "offset": [x, 43 + (row - 1) * 24]}})
        reel = [{"slots": {"type": "collection_panel", "size": [256, 168], "collection_name": "container_items", "controls": reel}}]
        x, width = positions[0] - 3, positions[-1] - positions[0] + 30
        reel.extend([{"selection_fill": picture("selection_fill", (width, 30), (x, 88), layer=2)}, {"selection": picture("selection", (width, 30), (x, 88), layer=32)}, {"pointer_left": picture("pointer_left", (7, 11), (x - 8, 98), layer=33)}, {"pointer_right": picture("pointer_right", (7, 11), (x + width + 1, 98), layer=33)}])
        ui["reels_" + str(count)] = {"type": "panel", "size": [256, 168], "visible": "($sw_rolls = " + str(count) + ")", "controls": reel}
    ui["roll_panel"] = {"type": "panel", "controls": [{"container_gamepad_helpers@common.container_gamepad_helpers": {}}, {"selected_item_details_factory@common.selected_item_details_factory": {"control_name": "@chest.selected_item_details"}}, {"root@common.root_panel": {"size": [256, 168], "layer": 1, "controls": [{"frame@soulwell.frame": {}}, {"title": label("$sw_roll_title", (210, 12), (21, 7), color=[1, 0.86, 0.43], text_alignment="center")}, {"status": label("Rolling...", (238, 10), (9, 23), shadow=False, text_alignment="center")}, {"hint": label("Each highlighted slot reveals one reward", (238, 8), (9, 33), shadow=False, font_scale_factor=0.75, text_alignment="center", color=[0.72, 0.81, 0.84])}, {"reels_1@soulwell.reels_1": {}}, {"reels_3@soulwell.reels_3": {}}, {"reels_5@soulwell.reels_5": {}}, {"close@soulwell.close": {}}, {"gamepad_cursor@common.gamepad_cursor_button": {}}]}}]}
    write_json(ROOT / "ui" / "soulwell_ui.json", ui)
    contains = lambda text: "(not ((#sw_container_title - '" + text + "') = #sw_container_title))"
    active = "(" + contains("Soul Well") + " and (" + " or ".join(contains("(" + str(count) + "x Roll)") for count in [1, 3, 5]) + "))"
    def title_binding(expression):
        return {"property_bag": {"#sw_container_title": "$container_title"}, "bindings": [{"binding_type": "view", "source_property_name": expression, "target_property_name": "#visible"}]}
    for name, fallback in [("classic_desktop", "chest.large_chest_panel"), ("classic_pocket", "pocket_containers.large_chest_panel"), ("data_desktop", "data_driven_container.desktop_panel"), ("data_pocket", "pocket_containers.data_driven_container_panel")]:
        children = [{"original@" + fallback: title_binding("(not " + active + ")")}]
        for count in [1, 3, 5]:
            children.append({"roll_" + str(count) + "@soulwell.roll_panel": {"$sw_rolls": count, "$sw_roll_title": "§lSOUL WELL §r§6• §l" + str(count) + "x ROLL", "visible": False, **title_binding("(" + contains("Soul Well") + " and " + contains("(" + str(count) + "x Roll)") + ")")}})
        ui[name + "_dispatch"] = {"type": "panel", "controls": children}
    write_json(ROOT / "ui" / "soulwell_ui.json", ui)
    for namespace, key, filename, prefix in [("chest", "large_chest_screen@common.inventory_screen_common", "chest_screen.json", "classic"), ("data_driven_container", "screen@common.inventory_screen_common", "data_driven_container_screen.json", "data")]:
        screen = {"$close_on_player_hurt|default": True, "$use_custom_pocket_toast|default": False, "close_on_player_hurt": "$close_on_player_hurt", "use_custom_pocket_toast": "$use_custom_pocket_toast", "variables": [{"requires": "$desktop_screen", "$screen_content": "soulwell." + prefix + "_desktop_dispatch", "$screen_bg_content": "common.screen_background", "$screen_background_alpha": 0.4}, {"requires": "$pocket_screen", "$screen_content": "soulwell." + prefix + "_pocket_dispatch", "$use_custom_pocket_toast": True}]}
        write_json(ROOT / "ui" / filename, {"namespace": namespace, key: screen})
    write_json(ROOT / "ui" / "_ui_defs.json", {"ui_defs": ["ui/soulwell_ui.json", "ui/chest_screen.json", "ui/data_driven_container_screen.json"]})

if __name__ == "__main__":
    textures()
    controls()
    print("SoulWell JSON UI and pixel textures rebuilt")
