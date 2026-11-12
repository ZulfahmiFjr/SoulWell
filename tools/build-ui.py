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

def prize_textures():
    def frame(name, width, height, footer=False):
        p = image(width, height)
        rect(p, (2, 2, width, height), (3, 8, 17, 220))
        for inset, color in [(0, (5, 15, 24)), (1, (27, 66, 78)), (2, (63, 118, 127)), (3, (13, 38, 50)), (4, (30, 67, 79)), (5, (17, 20, 43))]:
            rect(p, (inset, inset, width - 2 - inset, height - 2 - inset), color)
        rect(p, (5, 5, width - 7, 22), (29, 79, 95))
        rect(p, (5, 5, width - 7, 6), (65, 119, 130))
        rect(p, (5, 22, width - 7, 24), (6, 22, 32))
        for side in [0, 1]:
            for x, y in [(8, 8), (12, 7), (16, 10), (21, 8), (27, 13), (10, 15), (15, 17), (22, 16), (29, 18), (35, 18)]:
                px = x if not side else width - 14 - x
                rect(p, (px, y, px + 2, y + 3), (20, 62, 77))
                rect(p, (px + 2, y + 2, px + 4, y + 3), (47, 93, 107))
        if footer:
            rect(p, (5, height - 25, width - 7, height - 23), (43, 84, 96))
            rect(p, (5, height - 23, width - 7, height - 7), (10, 25, 35))
        png(name, p)
    frame("confirm_panel", 256, 196, True)
    frame("prize_panel", 300, 176)
    frame("detail_panel", 300, 166)
    def tile(name, fill, edge, width=16, height=16):
        p = image(width, height)
        rect(p, (0, 0, width, height), (5, 16, 28))
        rect(p, (1, 1, width - 1, height - 1), edge)
        rect(p, (2, 2, width - 2, height - 2), fill)
        rect(p, (2, 2, width - 2, 3), tuple(min(255, c + 12) for c in fill))
        rect(p, (2, height - 3, width - 2, height - 2), tuple(max(0, c - 9) for c in fill))
        png(name, p, 3)
    tile("prize_card", (17, 42, 55), (40, 79, 91))
    tile("prize_card_hover", (22, 63, 74), (111, 172, 172))
    tile("prize_card_pressed", (22, 91, 103), (234, 207, 114))
    tile("prize_info", (14, 35, 46), (49, 86, 96))
    tile("prize_button", (30, 59, 76), (69, 102, 113))
    tile("prize_button_hover", (22, 96, 108), (116, 218, 210))
    tile("prize_button_pressed", (15, 126, 130), (165, 241, 222))
    tile("prize_gold_button", (24, 45, 55), (192, 174, 106))
    tile("prize_gold_hover", (36, 78, 82), (252, 226, 145))
    tile("prize_gold_pressed", (22, 120, 126), (255, 238, 172))
    for rarity, edge, fill in [("common", (163, 182, 187), (40, 58, 68)), ("rare", (59, 161, 169), (22, 88, 100)), ("epic", (140, 82, 174), (65, 36, 88)), ("legendary", (188, 167, 91), (45, 47, 42))]:
        tile("tab_" + rarity, fill, tuple(int(c * 0.65) for c in edge))
        tile("tab_" + rarity + "_active", fill, edge)
        p = image(12, 12)
        rect(p, (1, 1, 11, 11), tuple(int(c * 0.48) for c in edge))
        rect(p, (2, 2, 10, 10), edge)
        rect(p, (3, 3, 9, 9), (18, 42, 53))
        rect(p, (4, 4, 8, 8), (0, 0, 0, 0))
        for x, y in [(0, 0), (8, 0), (0, 8), (8, 8)]:
            rect(p, (x, y, x + 4, y + 4), tuple(min(255, c + 38) for c in edge))
            rect(p, (x + 1, y + 1, x + 3, y + 3), tuple(int(c * 0.65) for c in edge))
        png("prize_frame_" + rarity, p, 5)
    p = image(16, 16)
    for box, color in [((2, 5, 14, 15), (210, 166, 77)), ((1, 4, 15, 7), (247, 210, 120)), ((7, 4, 9, 15), (188, 75, 64)), ((4, 1, 7, 4), (235, 122, 94)), ((9, 1, 12, 4), (235, 122, 94)), ((5, 2, 7, 3), (255, 214, 147)), ((9, 2, 11, 3), (255, 214, 147))]:
        rect(p, box, color)
    png("prize_gift", p)
    png("prize_unknown", p)
    for name, edge, fill in [("prize_scroll_track", (35, 65, 77), (10, 27, 39)), ("prize_scroll_handle", (85, 188, 190), (36, 137, 149))]:
        p = image(4, 12)
        rect(p, (0, 0, 4, 12), edge)
        rect(p, (1, 1, 3, 11), fill)
        png(name, p, 1)

def prize_controls(ui):
    def form_label(size, offset, scale=1, **extra):
        return label("#form_button_text", size, offset, font_scale_factor=scale, bindings=[{"binding_name": "#form_button_text", "binding_type": "collection", "binding_collection_name": "form_buttons"}], **extra)
    def form_icon(size, offset):
        return {"type": "image", "size": list(size), "offset": list(offset), "anchor_from": "top_left", "anchor_to": "top_left", "layer": 6, "bindings": [{"binding_name": "#form_button_texture", "binding_name_override": "#texture", "binding_type": "collection", "binding_collection_name": "form_buttons"}, {"binding_name": "#form_button_texture_file_system", "binding_name_override": "#texture_file_system", "binding_type": "collection", "binding_collection_name": "form_buttons"}]}
    details = [{"binding_type": "collection_details", "binding_collection_name": "form_buttons"}]
    def collection(size, controls, offset=(0, 0)):
        return {"type": "collection_panel", "size": list(size), "offset": list(offset), "anchor_from": "top_left", "anchor_to": "top_left", "collection_name": "form_buttons", "controls": controls}
    def scroll(size, offset, content):
        return {"size": list(size), "offset": list(offset), "anchor_from": "top_left", "anchor_to": "top_left", "$show_background": False, "$scrolling_content": content, "$scrolling_pane_size": ["100%", "100%"], "$scrolling_pane_size_touch": ["100%", "100%"], "$scroll_size": [4, "100%"], "$scroll_size_touch": [4, "100%"], "$scroll_bar_left_padding_size": [1, 0], "$scroll_bar_right_padding_size": [0, 0], "$scroll_box_visible_touch": True, "$scroll_track_image_control": "soulwell.prize_scroll_track", "$scroll_box_mouse_image_control": "soulwell.prize_scroll_handle", "$scroll_box_touch_image_control": "soulwell.prize_scroll_handle", "$allow_scrolling_even_when_content_fits": False}
    ui["prize_button_state"] = {"type": "panel", "controls": [{"background": {**picture("prize_button", ("100%", "100%")), "texture": "$sw_button_texture"}}, {"text": form_label(("100% - 12px", 12), (6, 6), 0.85, text_alignment="center")}]}
    ui["prize_button@common.button"] = {"size": [232, 24], "anchor_from": "top_left", "anchor_to": "top_left", "collection_name": "form_buttons", "$pressed_button_name": "button.form_button_click", "$sw_default_texture|default": "textures/ui/soulwell/prize_button", "$sw_hover_texture|default": "textures/ui/soulwell/prize_button_hover", "$sw_pressed_texture|default": "textures/ui/soulwell/prize_button_pressed", "bindings": details, "controls": [{"default@soulwell.prize_button_state": {"$sw_button_texture": "$sw_default_texture"}}, {"hover@soulwell.prize_button_state": {"$sw_button_texture": "$sw_hover_texture"}}, {"pressed@soulwell.prize_button_state": {"$sw_button_texture": "$sw_pressed_texture"}}]}
    confirm_buttons = [{"roll_" + str(i) + "@soulwell.prize_button": {"collection_index": i, "offset": [12, 58 + i * 29]}} for i in range(3)]
    confirm_buttons.append({"prizes@soulwell.prize_button": {"size": [104, 24], "offset": [76, 146], "collection_index": 3, "$sw_default_texture": "textures/ui/soulwell/prize_gold_button", "$sw_hover_texture": "textures/ui/soulwell/prize_gold_hover", "$sw_pressed_texture": "textures/ui/soulwell/prize_gold_pressed", "controls": [{"gift": picture("prize_gift", (12, 12), (7, 6), layer=8)}, {"default@soulwell.prize_button_state": {"$sw_button_texture": "$sw_default_texture"}}, {"hover@soulwell.prize_button_state": {"$sw_button_texture": "$sw_hover_texture"}}, {"pressed@soulwell.prize_button_state": {"$sw_button_texture": "$sw_pressed_texture"}}]}})
    ui["confirmation"] = {"type": "panel", "size": [256, 196], "layer": 5, "controls": [{"frame": picture("confirm_panel", (256, 196), layer=0)}, {"title": label("§lSOUL WELL", (204, 12), (24, 8), text_alignment="center", color=[1, 0.86, 0.43])}, {"greeting": label("#text", (144, 12), (14, 37), font_scale_factor=0.85, bindings=[{"binding_name": "#title_text"}, {"binding_type": "view", "source_property_name": "(#title_text - '" + TITLE + "')", "target_property_name": "#text"}])}, {"balance": label("#form_text", (80, 12), (150, 37), font_scale_factor=0.85, text_alignment="right", bindings=[{"binding_name": "#form_text"}])}, {"key": picture("key", (10, 10), (232, 37), layer=7)}, {"buttons": collection((256, 196), confirm_buttons)}, {"footer": label("Select your Roll or view Prizes below", (232, 10), (12, 179), font_scale_factor=0.75, color=[0.65, 0.75, 0.78], text_alignment="center")}, {"close@soulwell.close": {}}]}
    ui["prize_card_state"] = {"type": "panel", "controls": [{"background": {**picture("prize_card", ("100%", "100%")), "texture": "$sw_card_texture"}}, {"icon": form_icon((28, 28), (3, 3))}, {"frame": {**picture("prize_frame_legendary", (34, 34), layer=8), "texture": "$sw_prize_frame", "visible": "$sw_card_focused"}}, {"text": form_label((96, 32), (36, 3), 0.75)}]}
    ui["prize_card@common.button"] = {"size": [134, 34], "anchor_from": "top_left", "anchor_to": "top_left", "collection_name": "form_buttons", "$pressed_button_name": "button.form_button_click", "bindings": details, "controls": [{"default@soulwell.prize_card_state": {"$sw_card_texture": "textures/ui/soulwell/prize_card", "$sw_card_focused": False}}, {"hover@soulwell.prize_card_state": {"$sw_card_texture": "textures/ui/soulwell/prize_card_hover", "$sw_card_focused": True}}, {"pressed@soulwell.prize_card_state": {"$sw_card_texture": "textures/ui/soulwell/prize_card_pressed", "$sw_card_focused": True}}]}
    ui["prize_tab_state"] = {"type": "panel", "controls": [{"background": {**picture("tab_legendary", ("100%", "100%")), "texture": "$sw_tab_texture"}}, {"text": form_label(("100% - 4px", 12), (2, 4), 0.75, text_alignment="center")}]}
    ui["prize_tab@common.button"] = {"size": [71, 20], "anchor_from": "top_left", "anchor_to": "top_left", "collection_name": "form_buttons", "$pressed_button_name": "button.form_button_click", "bindings": details, "controls": [{"default@soulwell.prize_tab_state": {"$sw_tab_texture": "$sw_tab_default"}}, {"hover@soulwell.prize_tab_state": {"$sw_tab_texture": "$sw_tab_active"}}, {"pressed@soulwell.prize_tab_state": {"$sw_tab_texture": "$sw_tab_active"}}]}
    ui["prize_back_state"] = {"type": "panel", "controls": [{"background": {**picture("prize_button_hover", ("100%", "100%")), "texture": "$sw_back_texture", "alpha": "$sw_back_alpha"}}, {"text": form_label(("100% - 6px", 12), (3, 3), 0.9)}]}
    ui["prize_back@common.button"] = {"$sw_back_idle_alpha|default": 0, "size": [80, 18], "anchor_from": "top_left", "anchor_to": "top_left", "collection_name": "form_buttons", "$pressed_button_name": "button.form_button_click", "bindings": details, "controls": [{"default@soulwell.prize_back_state": {"$sw_back_alpha": "$sw_back_idle_alpha", "$sw_back_texture": "textures/ui/soulwell/prize_button"}}, {"hover@soulwell.prize_back_state": {"$sw_back_alpha": 1, "$sw_back_texture": "textures/ui/soulwell/prize_button_hover"}}, {"pressed@soulwell.prize_back_state": {"$sw_back_alpha": 1, "$sw_back_texture": "textures/ui/soulwell/prize_button_hover"}}]}
    ui["prize_scroll_track"] = {"type": "image", "texture": "textures/ui/soulwell/prize_scroll_track", "layer": 1}
    ui["prize_scroll_handle"] = {"type": "image", "texture": "textures/ui/soulwell/prize_scroll_handle", "size": [4, "100%"], "layer": 4}
    ui["prize_empty"] = label("No prizes in this rarity", (270, 30), (3, 8), font_scale_factor=0.85, text_alignment="center")
    colors = {"common": "§f", "rare": "§b", "epic": "§d", "legendary": "§6"}
    for active, count in [("common", 6), ("rare", 6), ("epic", 5), ("legendary", 4)]:
        tab_controls = []
        for i, rarity in enumerate(colors):
            tab_controls.append({"tab_" + rarity + "@soulwell.prize_tab": {"collection_index": i, "offset": [8 + i * 71, 27], "$sw_tab_default": "textures/ui/soulwell/tab_" + rarity + ("_active" if rarity == active else ""), "$sw_tab_active": "textures/ui/soulwell/tab_" + rarity + "_active"}})
        tab_controls.append({"back@soulwell.prize_back": {"collection_index": 4, "offset": [10, 153]}})
        ui["prize_grid_" + active] = collection((276, ((count + 1) // 2) * 38), [{"item_" + str(i) + "@soulwell.prize_card": {"collection_index": 5 + i, "offset": [(i % 2) * 138, (i // 2) * 38]}} for i in range(count)])
        ui["prize_list_" + active] = {"type": "panel", "size": [300, 176], "layer": 5, "$sw_prize_frame": "textures/ui/soulwell/prize_frame_" + active, "controls": [{"frame": picture("prize_panel", (300, 176), layer=0)}, {"title": label("§lPRIZE LIST", (248, 12), (24, 8), text_alignment="center", color=[1, 0.86, 0.43])}, {"tabs": collection((300, 176), tab_controls)}, {"chance": label("#form_text", (280, 12), (8, 51), font_scale_factor=0.85, text_alignment="center", bindings=[{"binding_name": "#form_text"}])}, {"hint": label("TAP FOR DETAILS", (280, 8), (8, 65), font_scale_factor=0.65, text_alignment="center", color=[0.48, 0.52, 0.65])}, {"list@common.scrolling_panel": scroll((280, 76), (10, 75), "soulwell.prize_grid_" + active)}, {"close@soulwell.close": {}}]}
    ui["prize_detail_info"] = collection((104, "100%c"), [{"text": {**form_label((98, "default"), (3, 3), 0.85), "collection_index": 4}}])
    for rarity in colors:
        meta = [{"heading": {"type": "panel", "size": [160, 48], "offset": [12, 34], "anchor_from": "top_left", "anchor_to": "top_left", "collection_index": 1, "bindings": details, "controls": [{"icon": form_icon((34, 34), (3, 3))}, {"frame": {**picture("prize_frame_legendary", (42, 42), layer=8), "texture": "textures/ui/soulwell/prize_frame_" + rarity}}, {"name": form_label((116, 30), (46, 4), 0.85)}]}}, {"rarity": {**form_label((116, 14), (58, 57), 0.8), "collection_index": 2}}, {"chance": {**form_label((160, 14), (12, 94), 1.0), "collection_index": 3}}, {"odds": {**form_label((160, 28), (12, 109), 0.75), "collection_index": 5}}, {"back@soulwell.prize_back": {"collection_index": 0, "offset": [12, 139], "size": [158, 18], "$sw_back_idle_alpha": 1}}]
        ui["prize_detail_" + rarity] = {"type": "panel", "size": [300, 166], "layer": 5, "controls": [{"frame": picture("detail_panel", (300, 166), layer=0)}, {"title": label("§lPRIZE DETAILS", (248, 12), (24, 8), text_alignment="center", color=[1, 0.86, 0.43])}, {"metadata": collection((300, 166), meta)}, {"info_background": picture("prize_info", (112, 124), (176, 32), layer=1)}, {"info@common.scrolling_panel": scroll((104, 116), (180, 36), "soulwell.prize_detail_info")}, {"close@soulwell.close": {}}]}
    contains = lambda marker: "(not ((#title_text - '" + marker + "') = #title_text))"
    is_confirm = contains("§e§lSoul Well Confirm§r")
    is_list = contains("§e§lSoulWell Prize List§r§r")
    is_detail = contains("§e§lSoulWell Prize Detail§r§r")
    markers = ["§e§lSoul Well Confirm§r", "§e§lSoulWell Prize List§r§r", "§e§lSoulWell Prize Detail§r§r"]
    original_visible = "(" + " and ".join("((#sw_form_title - '" + marker + "') = #sw_form_title)" for marker in markers) + ")"
    original = {"type": "panel", "size": [0, 0], "visible": False, "property_bag": {"#sw_form_title": ""}, "bindings": [{"binding_name": "#title_text", "binding_name_override": "#sw_form_title"}, {"binding_type": "view", "source_property_name": original_visible, "target_property_name": "#visible"}], "controls": [{"content@server_form.long_form": {}}]}
    dispatch = [{"original": original}, {"confirmation@soulwell.confirmation": {"visible": False, "bindings": gate(is_confirm)}}]
    for rarity in colors:
        dispatch.append({"list_" + rarity + "@soulwell.prize_list_" + rarity: {"visible": False, "bindings": gate("(" + is_list + " and " + contains("§0" + rarity) + ")")}})
        dispatch.append({"detail_" + rarity + "@soulwell.prize_detail_" + rarity: {"visible": False, "bindings": gate("(" + is_detail + " and " + contains("§0" + rarity) + ")")}})
    ui["form_dispatch"] = {"type": "panel", "size": [0, 0], "controls": dispatch}

def controls():
    is_soulwell = "(not ((#title_text - '§e§lSoul Well Confirm§r') = #title_text))"
    ui = {"namespace": "soulwell"}
    ui["form_dispatch"] = {"type": "panel", "size": [0, 0], "controls": [{"original@server_form.long_form": {"visible": False, "bindings": gate("(not " + is_soulwell + ")")}}, {"soulwell@soulwell.confirmation": {"visible": False, "bindings": gate(is_soulwell)}}]}
    ui["frame"] = picture("panel", (256, 168), layer=0)
    ui["close@common.button"] = {"size": [14, 14], "anchor_from": "top_right", "anchor_to": "top_right", "offset": [-9, 6], "layer": 40, "$pressed_button_name": "button.menu_exit", "controls": [{"default": picture("close", (14, 14))}, {"hover": picture("close_hover", (14, 14))}, {"pressed": picture("close_pressed", (14, 14))}]}
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
    prize_controls(ui)
    write_json(ROOT / "ui" / "soulwell_ui.json", ui)
    contains = lambda text: "(not ((#sw_container_title - '" + text + "') = #sw_container_title))"
    def title_binding(expression):
        return {"property_bag": {"#sw_container_title": "$container_title"}, "bindings": [{"binding_type": "view", "source_property_name": expression, "target_property_name": "#visible"}]}
    for name, fallback in [("classic_desktop", "chest.large_chest_panel"), ("classic_pocket", "pocket_containers.large_chest_panel"), ("data_desktop", "data_driven_container.desktop_panel"), ("data_pocket", "pocket_containers.data_driven_container_panel")]:
        original = {"type": "panel", "visible": False, **title_binding("((#sw_container_title - '§l§fSoul Well (') = #sw_container_title)"), "controls": [{"inventory@" + fallback: {}}]}
        children = [{"original": original}]
        for count in [1, 3, 5]:
            children.append({"roll_" + str(count) + "@soulwell.roll_panel": {"$sw_rolls": count, "$sw_roll_title": "§lSOUL WELL §r§6§l" + str(count) + "x ROLL", "visible": False, **title_binding("(" + contains("Soul Well") + " and " + contains("(" + str(count) + "x Roll)") + ")")}})
        ui[name + "_dispatch"] = {"type": "panel", "controls": children}
    write_json(ROOT / "ui" / "soulwell_ui.json", ui)
    for namespace, key, filename, prefix in [("chest", "large_chest_screen@common.inventory_screen_common", "chest_screen.json", "classic"), ("data_driven_container", "screen@common.inventory_screen_common", "data_driven_container_screen.json", "data")]:
        screen = {"$close_on_player_hurt|default": True, "$use_custom_pocket_toast|default": False, "close_on_player_hurt": "$close_on_player_hurt", "use_custom_pocket_toast": "$use_custom_pocket_toast", "variables": [{"requires": "$desktop_screen", "$screen_content": "soulwell." + prefix + "_desktop_dispatch", "$screen_bg_content": "common.screen_background", "$screen_background_alpha": 0.4}, {"requires": "$pocket_screen", "$screen_content": "soulwell." + prefix + "_pocket_dispatch", "$use_custom_pocket_toast": True}]}
        write_json(ROOT / "ui" / filename, {"namespace": namespace, key: screen})
    write_json(ROOT / "ui" / "_ui_defs.json", {"ui_defs": ["ui/soulwell_ui.json", "ui/chest_screen.json", "ui/data_driven_container_screen.json"]})

if __name__ == "__main__":
    textures()
    prize_textures()
    controls()
    print("SoulWell JSON UI and pixel textures rebuilt")
