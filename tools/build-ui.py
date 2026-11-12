import json
from copy import deepcopy
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
    rect(p, (2, 2, 256, 168), (28, 34, 25, 210))
    rect(p, (0, 0, 254, 166), (35, 43, 31))
    rect(p, (1, 1, 253, 165), (117, 130, 102))
    rect(p, (2, 2, 252, 164), (207, 214, 193))
    rect(p, (3, 3, 251, 163), (81, 93, 68))
    rect(p, (4, 4, 250, 162), (54, 58, 50))
    rect(p, (4, 4, 250, 19), (61, 85, 50))
    rect(p, (4, 4, 250, 5), (156, 178, 129))
    rect(p, (4, 19, 250, 20), (37, 47, 32))
    rect(p, (4, 20, 250, 41), (46, 53, 40))
    rect(p, (4, 41, 250, 42), (31, 40, 27))
    png("panel", p)
    for name, fill, edge, top in [("button", (64, 87, 55), (177, 192, 148), (195, 211, 167)), ("button_hover", (83, 111, 66), (188, 214, 151), (213, 233, 184)), ("button_pressed", (52, 76, 44), (164, 190, 131), (245, 249, 231))]:
        p = image(16, 16)
        rect(p, (0, 0, 16, 16), (34, 43, 28))
        rect(p, (1, 1, 15, 15), edge)
        rect(p, (2, 2, 14, 14), fill)
        rect(p, (2, 2, 14, 3), top)
        rect(p, (2, 13, 14, 14), (43, 59, 34))
        png(name, p, 3)
    p = image(24, 24)
    rect(p, (0, 0, 24, 24), (42, 48, 37))
    rect(p, (1, 0, 23, 24), (137, 151, 119))
    rect(p, (2, 1, 22, 23), (58, 67, 49))
    rect(p, (3, 1, 21, 2), (165, 180, 143))
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
    for name, fill, cross in [("close", (187, 195, 174), (43, 54, 34)), ("close_hover", (106, 135, 85), (245, 249, 231)), ("close_pressed", (67, 96, 54), (245, 249, 231))]:
        p = image(14, 14)
        rect(p, (0, 0, 14, 14), (42, 50, 36))
        rect(p, (1, 1, 13, 13), (126, 141, 107))
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
        rect(p, (2, 2, width, height), (28, 34, 25, 220))
        for inset, color in [(0, (35, 43, 31)), (1, (117, 130, 102)), (2, (207, 214, 193)), (3, (81, 93, 68)), (4, (164, 177, 143)), (5, (54, 58, 50))]:
            rect(p, (inset, inset, width - 2 - inset, height - 2 - inset), color)
        rect(p, (5, 5, width - 7, 22), (190, 198, 179))
        rect(p, (5, 5, width - 7, 6), (230, 235, 218))
        rect(p, (5, 22, width - 7, 24), (89, 104, 74))
        for side in [0, 1]:
            for x, y in [(8, 8), (12, 7), (16, 10), (21, 8), (27, 13), (10, 15), (15, 17), (22, 16), (29, 18), (35, 18)]:
                px = x if not side else width - 14 - x
                rect(p, (px, y, px + 2, y + 3), (147, 163, 129))
                rect(p, (px + 2, y + 2, px + 4, y + 3), (210, 220, 194))
        if footer:
            rect(p, (5, height - 25, width - 7, height - 23), (154, 170, 134))
            rect(p, (5, height - 23, width - 7, height - 7), (45, 52, 39))
        png(name, p)
    frame("confirm_panel", 256, 196, True)
    frame("prize_panel", 300, 176)
    frame("detail_panel", 300, 166)
    def tile(name, fill, edge, width=16, height=16):
        p = image(width, height)
        rect(p, (0, 0, width, height), (34, 43, 28))
        rect(p, (1, 1, width - 1, height - 1), edge)
        rect(p, (2, 2, width - 2, height - 2), fill)
        rect(p, (2, 2, width - 2, 3), tuple(min(255, c + 12) for c in fill))
        rect(p, (2, height - 3, width - 2, height - 2), tuple(max(0, c - 9) for c in fill))
        png(name, p, 3)
    tile("prize_card", (45, 52, 40), (147, 162, 127))
    tile("prize_card_hover", (56, 81, 58), (183, 209, 150))
    tile("prize_card_pressed", (66, 96, 59), (211, 230, 181))
    tile("prize_info", (43, 49, 37), (142, 157, 121))
    p = image(16, 16, (22, 24, 20, 255))
    rect(p, (0, 0, 16, 1), (12, 14, 11))
    rect(p, (0, 0, 1, 16), (12, 14, 11))
    rect(p, (0, 15, 16, 16), (79, 85, 70))
    rect(p, (15, 0, 16, 16), (79, 85, 70))
    rect(p, (1, 1, 15, 2), (17, 20, 16))
    rect(p, (1, 1, 2, 15), (17, 20, 16))
    png("prize_scroll_background", p, 2)
    tile("prize_button", (64, 87, 55), (177, 192, 148))
    tile("prize_button_hover", (83, 111, 66), (188, 214, 151))
    tile("prize_button_pressed", (52, 76, 44), (213, 233, 184))
    tile("prize_gold_button", (70, 96, 57), (192, 208, 155))
    tile("prize_gold_hover", (83, 112, 65), (222, 237, 188))
    tile("prize_gold_pressed", (60, 88, 52), (201, 222, 164))
    for rarity, edge, fill in [("common", (178, 189, 163), (67, 72, 61)), ("rare", (104, 161, 125), (45, 77, 64)), ("epic", (154, 122, 161), (69, 57, 72)), ("legendary", (193, 177, 113), (79, 73, 49))]:
        tile("tab_" + rarity, fill, tuple(int(c * 0.65) for c in edge))
        tile("tab_" + rarity + "_hover", tuple(min(255, c + 7) for c in fill), edge)
        p = image(16, 16, (34, 43, 28, 255))
        rect(p, (1, 1, 15, 15), (222, 237, 188))
        rect(p, (3, 3, 13, 13), tuple(min(255, c + 12) for c in fill))
        rect(p, (3, 3, 13, 4), (150, 174, 121))
        rect(p, (3, 11, 13, 13), edge)
        png("tab_" + rarity + "_active", p, 4)
        p = image(12, 12)
        rect(p, (1, 1, 11, 11), tuple(int(c * 0.48) for c in edge))
        rect(p, (2, 2, 10, 10), edge)
        rect(p, (3, 3, 9, 9), (48, 56, 44))
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
    p = image(12, 12)
    rect(p, (3, 6, 11, 8), (38, 82, 94))
    for step in range(4):
        rect(p, (2 + step, 6 - step, 4 + step, 8 - step), (38, 82, 94))
        rect(p, (2 + step, 6 + step, 4 + step, 8 + step), (38, 82, 94))
    rect(p, (3, 5, 11, 7), (214, 235, 238))
    for step in range(4):
        rect(p, (2 + step, 5 - step, 4 + step, 7 - step), (214, 235, 238))
        rect(p, (2 + step, 5 + step, 4 + step, 7 + step), (214, 235, 238))
    png("back_arrow", p)
    for name, edge, fill in [("prize_scroll_track", (103, 119, 87), (43, 52, 35)), ("prize_scroll_handle", (179, 204, 145), (104, 140, 74))]:
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
    ui["prize_button_state"] = {"type": "panel", "controls": [{"background": {**picture("prize_button", ("100%", "100%")), "texture": "$sw_button_texture"}}, {"text": {**form_label(("100% - 12px", "default"), (0, 0), 0.85, text_alignment="center"), "anchor_from": "center", "anchor_to": "center"}}]}
    ui["prize_list_button_state"] = {"type": "panel", "controls": [{"background": {**picture("prize_gold_button", ("100%", "100%")), "texture": "$sw_button_texture"}}, {"text": {**form_label(("100% - 26px", "default"), (-4, 0), 1.0, text_alignment="center"), "anchor_from": "right_middle", "anchor_to": "right_middle"}}]}
    ui["prize_button@common.button"] = {"size": [232, 24], "anchor_from": "top_left", "anchor_to": "top_left", "collection_name": "form_buttons", "$pressed_button_name": "button.form_button_click", "$sw_default_texture|default": "textures/ui/soulwell/prize_button", "$sw_hover_texture|default": "textures/ui/soulwell/prize_button_hover", "$sw_pressed_texture|default": "textures/ui/soulwell/prize_button_pressed", "bindings": details, "controls": [{"default@soulwell.prize_button_state": {"$sw_button_texture": "$sw_default_texture"}}, {"hover@soulwell.prize_button_state": {"$sw_button_texture": "$sw_hover_texture"}}, {"pressed@soulwell.prize_button_state": {"$sw_button_texture": "$sw_pressed_texture"}}]}
    confirm_buttons = [{"roll_" + str(i) + "@soulwell.prize_button": {"collection_index": i, "offset": [12, 51.5 + i * 29]}} for i in range(3)]
    confirm_buttons.append({"prizes@soulwell.prize_button": {"size": [104, 24], "offset": [76, 141.75], "collection_index": 3, "$sw_default_texture": "textures/ui/soulwell/prize_gold_button", "$sw_hover_texture": "textures/ui/soulwell/prize_gold_hover", "$sw_pressed_texture": "textures/ui/soulwell/prize_gold_pressed", "controls": [{"gift": picture("prize_gift", (12, 12), (6, 6), layer=8)}, {"default@soulwell.prize_list_button_state": {"$sw_button_texture": "$sw_default_texture"}}, {"hover@soulwell.prize_list_button_state": {"$sw_button_texture": "$sw_hover_texture"}}, {"pressed@soulwell.prize_list_button_state": {"$sw_button_texture": "$sw_pressed_texture"}}]}})
    ui["confirmation"] = {"type": "panel", "size": [256, 196], "layer": 5, "controls": [{"frame": picture("confirm_panel", (256, 196), layer=0)}, {"title": label("§lSOUL WELL", (204, 12), (24, 8), text_alignment="center", color=[0.16, 0.22, 0.12])}, {"greeting": label("#text", (144, "default"), (14, 32), font_scale_factor=0.85, bindings=[{"binding_name": "#title_text"}, {"binding_type": "view", "source_property_name": "(#title_text - '" + TITLE + "')", "target_property_name": "#text"}])}, {"balance": label("#form_text", (80, "default"), (150, 32), font_scale_factor=0.85, text_alignment="right", bindings=[{"binding_name": "#form_text"}])}, {"key": picture("key", (10, 10), (232, 31.25), layer=7)}, {"roll_background": picture("prize_scroll_background", (240, 88), (8, 48.5), layer=1)}, {"buttons": collection((256, 196), confirm_buttons)}, {"footer": label("Select your Roll or view Prizes below", (232, "default"), (0, -15), anchor_from="bottom_middle", anchor_to="center", font_scale_factor=0.75, color=[0.72, 0.77, 0.66], text_alignment="center")}, {"close@soulwell.close": {}}]}
    ui["prize_card_state"] = {"type": "panel", "controls": [{"background": {**picture("prize_card", ("100%", "100%")), "texture": "$sw_card_texture"}}, {"icon": form_icon((28, 28), (3, 3))}, {"frame": {**picture("prize_frame_legendary", (34, 34), layer=8), "texture": "$sw_prize_frame", "visible": "$sw_card_focused"}}, {"text": {**form_label((96, "default"), (36, 0), 0.75), "anchor_from": "left_middle", "anchor_to": "left_middle"}}]}
    ui["prize_card@common.button"] = {"size": [134, 34], "anchor_from": "top_left", "anchor_to": "top_left", "collection_name": "form_buttons", "$pressed_button_name": "button.form_button_click", "bindings": details, "controls": [{"default@soulwell.prize_card_state": {"$sw_card_texture": "textures/ui/soulwell/prize_card", "$sw_card_focused": False}}, {"hover@soulwell.prize_card_state": {"$sw_card_texture": "textures/ui/soulwell/prize_card_hover", "$sw_card_focused": True}}, {"pressed@soulwell.prize_card_state": {"$sw_card_texture": "textures/ui/soulwell/prize_card_pressed", "$sw_card_focused": True}}]}
    ui["prize_tab_state"] = {"type": "panel", "controls": [{"background": {**picture("tab_legendary", ("100%", "100%")), "texture": "$sw_tab_texture"}}, {"text": {**form_label(("100% - 4px", "default"), (0, 0), 0.9, text_alignment="center"), "anchor_from": "center", "anchor_to": "center"}}]}
    ui["prize_tab@common.button"] = {"size": [71, 22], "locked_control": "locked", "anchor_from": "top_left", "anchor_to": "top_left", "collection_name": "form_buttons", "$pressed_button_name": "button.form_button_click", "bindings": details, "controls": [{"default@soulwell.prize_tab_state": {"$sw_tab_texture": "$sw_tab_default"}}, {"hover@soulwell.prize_tab_state": {"$sw_tab_texture": "$sw_tab_hover"}}, {"pressed@soulwell.prize_tab_state": {"$sw_tab_texture": "$sw_tab_active"}}, {"locked@soulwell.prize_tab_state": {"$sw_tab_texture": "$sw_tab_active"}}]}
    ui["prize_back_state"] = {"type": "panel", "controls": [{"background": {**picture("prize_button_hover", ("100%", "100%")), "texture": "$sw_back_texture", "alpha": "$sw_back_alpha"}}, {"arrow": {**picture("back_arrow", (12, 12), (6, 0), layer=8), "anchor_from": "left_middle", "anchor_to": "left_middle"}}, {"text": {**form_label(("100% - 40px", "default"), (0, 0), 0.9, text_alignment="center"), "anchor_from": "center", "anchor_to": "center"}}]}
    ui["prize_back@common.button"] = {"$sw_back_idle_alpha|default": 1, "size": [80, 18], "anchor_from": "top_left", "anchor_to": "top_left", "collection_name": "form_buttons", "$pressed_button_name": "button.form_button_click", "bindings": details, "controls": [{"default@soulwell.prize_back_state": {"$sw_back_alpha": "$sw_back_idle_alpha", "$sw_back_texture": "textures/ui/soulwell/prize_button"}}, {"hover@soulwell.prize_back_state": {"$sw_back_alpha": 1, "$sw_back_texture": "textures/ui/soulwell/prize_button_hover"}}, {"pressed@soulwell.prize_back_state": {"$sw_back_alpha": 1, "$sw_back_texture": "textures/ui/soulwell/prize_button_hover"}}]}
    ui["prize_scroll_track"] = {"type": "image", "texture": "textures/ui/soulwell/prize_scroll_track", "layer": 1}
    ui["prize_scroll_handle"] = {"type": "image", "texture": "textures/ui/soulwell/prize_scroll_handle", "size": [4, "100%"], "layer": 4}
    ui["prize_empty"] = label("No prizes in this rarity", (270, 30), (3, 8), font_scale_factor=0.85, text_alignment="center")
    colors = {"common": "§f", "rare": "§b", "epic": "§d", "legendary": "§6"}
    for active, count in [("common", 6), ("rare", 6), ("epic", 5), ("legendary", 4)]:
        tab_controls = []
        for i, rarity in enumerate(colors):
            tab_controls.append({"tab_" + rarity + "@soulwell.prize_tab": {"collection_index": i, "offset": [8 + i * 71, 26], "enabled": rarity != active, "$focus_enabled": rarity != active, "$sw_tab_default": "textures/ui/soulwell/tab_" + rarity + ("_active" if rarity == active else ""), "$sw_tab_hover": "textures/ui/soulwell/tab_" + rarity + "_hover", "$sw_tab_active": "textures/ui/soulwell/tab_" + rarity + "_active"}})
        tab_controls.append({"back@soulwell.prize_back": {"collection_index": 4, "offset": [10, 149]}})
        ui["prize_grid_" + active] = collection((276, ((count + 1) // 2) * 38), [{"item_" + str(i) + "@soulwell.prize_card": {"collection_index": 5 + i, "offset": [(i % 2) * 138, (i // 2) * 38]}} for i in range(count)])
        ui["prize_list_" + active] = {"type": "panel", "size": [300, 176], "layer": 5, "$sw_prize_frame": "textures/ui/soulwell/prize_frame_" + active, "controls": [{"frame": picture("prize_panel", (300, 176), layer=0)}, {"title": label("§lPRIZE LIST", (248, 12), (24, 8), text_alignment="center", color=[0.16, 0.22, 0.12])}, {"tabs": collection((300, 176), tab_controls)}, {"chance": label("#form_text", (280, "default"), (8, 50), font_scale_factor=0.85, text_alignment="center", bindings=[{"binding_name": "#form_text"}])}, {"hint": label("TAP FOR DETAILS", (280, "default"), (8, 60), font_scale_factor=0.65, text_alignment="center", color=[0.65, 0.70, 0.58])}, {"scroll_background": picture("prize_scroll_background", (284, 78), (8, 68.75), layer=1)}, {"list@common.scrolling_panel": scroll((280, 72), (10, 71.75), "soulwell.prize_grid_" + active)}, {"close@soulwell.close": {}}]}
    ui["prize_detail_info"] = collection((104, "100%c"), [{"text": {**form_label((98, "default"), (3, 3), 0.85), "collection_index": 4}}])
    for rarity in colors:
        meta = [{"heading": {"type": "panel", "size": [160, 48], "offset": [12, 34], "anchor_from": "top_left", "anchor_to": "top_left", "collection_index": 1, "bindings": details, "controls": [{"icon": form_icon((34, 34), (3, 3))}, {"frame": {**picture("prize_frame_legendary", (42, 42), layer=8), "texture": "textures/ui/soulwell/prize_frame_" + rarity}}, {"name": form_label((116, 30), (46, 4), 0.85)}]}}, {"rarity": {**form_label((116, 14), (58, 57), 0.8), "collection_index": 2}}, {"chance": {**form_label((160, 14), (12, 94), 1.0), "collection_index": 3}}, {"odds": {**form_label((160, 28), (12, 109), 0.75), "collection_index": 5}}, {"back@soulwell.prize_back": {"collection_index": 0, "offset": [12, 139], "size": [158, 18], "$sw_back_idle_alpha": 1}}]
        ui["prize_detail_" + rarity] = {"type": "panel", "size": [300, 166], "layer": 5, "controls": [{"frame": picture("detail_panel", (300, 166), layer=0)}, {"title": label("§lPRIZE DETAILS", (248, 12), (24, 8), text_alignment="center", color=[0.16, 0.22, 0.12])}, {"metadata": collection((300, 166), meta)}, {"info_background": picture("prize_info", (112, 124), (176, 32), layer=1)}, {"info@common.scrolling_panel": scroll((104, 116), (180, 36), "soulwell.prize_detail_info")}, {"close@soulwell.close": {}}]}
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

def localized_controls(ui):
    text = json.loads((ROOT.parents[1] / "resources" / "languages" / "ind.json").read_text())["messages"]
    ui["prize_list_button_state_ind"] = deepcopy(ui["prize_list_button_state"])
    ui["prize_list_button_state_ind"]["controls"][1]["text"]["font_scale_factor"] = 0.8
    ui["confirmation_ind"] = deepcopy(ui["confirmation"])
    confirm = {key: value for control in ui["confirmation_ind"]["controls"] for key, value in control.items()}
    confirm["footer"]["text"] = text["ui.footer"]
    confirm["greeting"]["size"][0] = 116
    confirm["balance"]["size"][0] = 100
    confirm["balance"]["offset"][0] = 130
    gift = confirm["buttons"]["controls"][3]["prizes@soulwell.prize_button"]
    gift["controls"] = [{key + "_ind" if key.endswith("@soulwell.prize_list_button_state") else key: value for key, value in control.items()} for control in gift["controls"]]
    ui["prize_empty_ind"] = deepcopy(ui["prize_empty"])
    ui["prize_empty_ind"]["text"] = text["ui.empty"]
    for rarity in ["common", "rare", "epic", "legendary"]:
        ui["prize_grid_" + rarity + "_ind"] = deepcopy(ui["prize_grid_" + rarity])
        for kind, heading in [("list", "ui.prize_heading"), ("detail", "ui.details_heading")]:
            original = "prize_" + kind + "_" + rarity
            ui[original + "_ind"] = deepcopy(ui[original])
            controls = {key: value for control in ui[original + "_ind"]["controls"] for key, value in control.items()}
            controls["title"]["text"] = text[heading]
            if kind == "list":
                controls["hint"]["text"] = text["ui.tap"]
                controls["list@common.scrolling_panel"]["$scrolling_content"] += "_ind"
    ui["roll_panel_ind"] = deepcopy(ui["roll_panel"])
    controls = {key: value for control in ui["roll_panel_ind"]["controls"][2]["root@common.root_panel"]["controls"] for key, value in control.items()}
    controls["status"]["text"] = text["ui.rolling"]
    controls["hint"]["text"] = text["ui.roll_hint"]
    controls["hint"]["font_scale_factor"] = 0.65
    confirm_title = text["ui.confirm_title"] + "§r§r"
    confirm["greeting"]["bindings"][-1]["source_property_name"] = "(#title_text - '" + confirm_title + "')"
    contains = lambda marker: "(not ((#sw_form_title - '" + marker + "') = #sw_form_title))"
    def wrapper(reference, expression):
        return {"type": "panel", "size": [0, 0], "visible": False, "property_bag": {"#sw_form_title": ""}, "bindings": [{"binding_name": "#title_text", "binding_name_override": "#sw_form_title"}, {"binding_type": "view", "source_property_name": expression, "target_property_name": "#visible"}], "controls": [{"content@" + reference: {}}]}
    routes = []
    markers = [TITLE, confirm_title, "§e§lSoulWell Prize List§r§r", text["ui.prize_form_title"], "§e§lSoulWell Prize Detail§r§r", text["ui.detail_form_title"]]
    ordinary = "(" + " and ".join("((#sw_form_title - '" + marker + "') = #sw_form_title)" for marker in markers) + ")"
    routes.append({"original": wrapper("server_form.long_form", ordinary)})
    for suffix, confirm_marker, list_marker, detail_marker in [("", TITLE, markers[2], markers[4]), ("_ind", confirm_title, markers[3], markers[5])]:
        routes.append({"confirmation" + suffix: wrapper("soulwell.confirmation" + suffix, contains(confirm_marker))})
        for rarity in ["common", "rare", "epic", "legendary"]:
            for kind, marker in [("list", list_marker), ("detail", detail_marker)]:
                name = kind + "_" + rarity + suffix
                routes.append({name: wrapper("soulwell.prize_" + name, "(" + contains(marker) + " and " + contains("§0" + rarity) + ")")})
    ui["form_dispatch"]["controls"] = routes

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
    localized_controls(ui)
    write_json(ROOT / "ui" / "soulwell_ui.json", ui)
    contains = lambda text: "(not ((#sw_container_title - '" + text + "') = #sw_container_title))"
    def title_binding(expression):
        return {"property_bag": {"#sw_container_title": "$container_title"}, "bindings": [{"binding_type": "view", "source_property_name": expression, "target_property_name": "#visible"}]}
    for name, fallback in [("classic_desktop", "chest.large_chest_panel"), ("classic_pocket", "pocket_containers.large_chest_panel"), ("data_desktop", "data_driven_container.desktop_panel"), ("data_pocket", "pocket_containers.data_driven_container_panel")]:
        original = {"type": "panel", "visible": False, **title_binding("((#sw_container_title - '§l§fSoul Well (') = #sw_container_title)"), "controls": [{"inventory@" + fallback: {}}]}
        children = [{"original": original}]
        for count in [1, 3, 5]:
            for suffix, word in [("", "Roll"), ("_ind", "Putar")]:
                children.append({"roll_" + str(count) + suffix + "@soulwell.roll_panel" + suffix: {"$sw_rolls": count, "$sw_roll_title": "§lSOUL WELL §r§6§l" + str(count) + "x " + word.upper(), "visible": False, **title_binding("(" + contains("Soul Well") + " and " + contains("(" + str(count) + "x " + word + ")") + ")")}})
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
