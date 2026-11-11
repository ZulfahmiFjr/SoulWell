# Nautic SoulWell UI

Version 1.7.2 provides a resource-pack interface based on the approved navy, cyan, and gold design. The confirmation menu shows the player's name, current Soul Keys, and three buttons for 1x, 3x, and 5x rolls. All three choices fit in a 256 by 168 GUI-unit panel. The close button, Escape, or the mobile back action cancels the confirmation without spending keys. The balance label and key icon are inset an additional six GUI units from the right edge.

The inventory design uses Minecraft's native item renderer and stack-count bindings. Five visible rows surround the selected reward row. One hidden upstream row remains part of the existing six-row server animation, so the animation timing, prediction, payout, and refund logic continue to use their original slots.

| Roll mode | Server columns | Selected inventory slots, zero-based |
| --- | --- | --- |
| 1x | 4 | 31 |
| 3x | 2, 4, 6 | 29, 31, 33 |
| 5x | 0, 2, 4, 6, 8 | 27, 29, 31, 33, 35 |

The custom chest panel requires both the `Soul Well` text marker and a supported `(1x Roll)`, `(3x Roll)`, or `(5x Roll)` marker. It uses the string-subtraction marker test from the earlier working SoulWell pack rather than equality against a complete formatted title. Classic large-chest and data-driven container screen definitions both apply these checks and are explicitly registered alongside the SoulWell namespace in `_ui_defs.json`. Other titles retain the respective desktop or pocket panel. The pack does not redefine the vanilla item grid or common panel. The confirmation route recognizes the dedicated SoulWell title prefix, while other forms use the dispatcher already supplied by the server's resource packs.

In the inspected MCBE ARM64 image, the container title setup function constructs a wrapper with `__string`, `__rawtext`, and `value` fields before assigning `$container_title`. Its screen factory contains separate classic chest and data-driven routes. The installed InvMenu sends `WindowTypes::CONTAINER`, whose value in the installed protocol library is 0; it is not changed by this patch. Static analysis of bounded original instruction ranges informed these compatibility changes. It does not establish which branch ran on the user's device or prove that the wrapped type was the only reason full-title equality failed. The user confirmed the confirmation UI worked in 1.7.1 while the inventory remained standard.

## Resource pack lifecycle

The existing `classic-chest-rp` setting enables both custom screens. When disabled, SoulWell keeps its standard form and decorated inventory display. Existing configuration, rewards, balances, and altar coordinates are preserved.

On startup, `ResourcePackService` builds `plugin_data/SoulWell/resource.zip` from the bundled `resources/ClassicLargeChest` directory. It preserves shared `_ui_defs` entries and the complete existing `server_form.json` definitions, wraps the current long-form route, and keeps custom forms routed to their previous handler. It registers the SoulWell pack ahead of existing packs through the public resource-pack manager API. The previous stack entries retain their relative priority. On shutdown, the service removes its own pack instance and restores the previous resource requirement.

The existing pack UUID is retained, and its header and module versions are bumped to 1.7.2. Reconnect after deployment so Bedrock can download the updated pack. Clients that retain a stale copy may need to remove only the cached Nautic SoulWell UI pack and reconnect.

The Pets plugin reorders packs one tick after enable and in its HIGHEST-priority resource-pack offer listener. Version 1.7.0 did not account for those later changes: the actual login offer selected Pets' `_ui_defs.json`, which omitted the SoulWell namespace. The observed client therefore showed standard forms and reported unresolved UI properties. Version 1.7.1 loads after Pets through an optional dependency, reapplies SoulWell priority after startup, and handles the login offer after Pets at the same event priority. Encryption keys, the download requirement, and all other pack objects remain intact. Shared Pets player models and renderer definitions are not copied or replaced.

`plugin_data/SoulWell/resource-pack-startup.json` records the runtime order after startup. `resource-pack-client-offer.json` records the corrected order when an actual login offer is handled. These reports contain pack metadata only. A login-offer report establishes what the server offered, not successful client rendering.

## Build

```sh
python3 tools/build-ui.py
php -d phar.readonly=0 tools/build.php output/SoulWell_v1.7.2.phar
```

The UI asset builder uses only the Python standard library. The PHP build packages source, bundled libraries, and resources into a SHA-256 signed PHAR. It does not start a server or alter production data.

## Validation limits

PHP syntax, pack parsing, the generated form payload, resource-pack registration and removal, existing form routes, UI references, slot mapping, and simulated layout can be checked on the VPS. Simulation checks do not establish actual Bedrock rendering, inventory-item binding, touch behavior, sound, or successful client resource-pack loading. Those require an in-game check of the confirmation and all three roll modes, plus a normal chest and another plugin's form. Exact pixel matching also depends on client GUI scale, safe area, and font rendering.
