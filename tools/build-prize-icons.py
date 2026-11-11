import json
import urllib.request
from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
ICONS = ["iron_sword", "iron_pickaxe", "bow_standby", "diamond_sword", "diamond_axe", "diamond_chestplate", "diamond_pickaxe", "netherite_sword", "netherite_axe", "netherite_pickaxe", "netherite_chestplate"]
CACHE = ROOT / "tmp" / "vanilla-icons"
OUTPUT = ROOT / "resources" / "ClassicLargeChest" / "textures" / "ui" / "soulwell" / "items"

def build():
    CACHE.mkdir(parents=True, exist_ok=True)
    OUTPUT.mkdir(parents=True, exist_ok=True)
    mapping = json.loads((ROOT / "resources" / "item_icons.json").read_text())
    for name in ICONS:
        source = CACHE / (name + ".png")
        if not source.exists():
            source.write_bytes(urllib.request.urlopen("https://raw.githubusercontent.com/Mojang/bedrock-samples/main/resource_pack/textures/items/" + name + ".png", timeout=20).read())
        pixels = Image.open(source).convert("RGBA")
        for y in range(pixels.height):
            for x in range(pixels.width):
                r, g, b, a = pixels.getpixel((x, y))
                if a == 0 or max(r, g, b) < 38:
                    continue
                shine = (x + y * 2) % 13 in [2, 3, 4]
                tint = (164, 106, 205) if shine else (118, 69, 157)
                mix = 0.62 if shine else 0.38
                pixels.putpixel((x, y), tuple(round(c * (1 - mix) + t * mix) for c, t in zip((r, g, b), tint)) + (a,))
        pixels.save(OUTPUT / (name + ".png"), optimize=True)
        mapping["enchanted:" + ("bow" if name == "bow_standby" else name)] = "textures/ui/soulwell/items/" + name
    (ROOT / "resources" / "item_icons.json").write_text(json.dumps(dict(sorted(mapping.items())), indent=2) + "\n")
    print("Built", len(ICONS), "enchanted prize icons from Mojang Bedrock sample textures")

if __name__ == "__main__":
    build()
