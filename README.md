# CustomVanillaEnchants
[![](https://poggit.pmmp.io/shield.state/CustomVanillaEnchants)](https://poggit.pmmp.io/p/CustomVanillaEnchants)

CustomVanillaEnchants lets you configure value expressions for registered enchantments.

## How to Use
Run `/cve list` in console or in-game.
```
> cve list
/cve list <enchantment>
Available Enchantments: blast_protection, feather_falling, fire_aspect, fire_protection, knockback, projectile_protection, protection, sharpness
```

To view configurable entries of the knockback enchantment, run `/cve list knockback`.
```
> cve list knockback
Enchantment ("knockback") Configuration
damage_bonus: 0
force: level * 0.5
```

To change knockback enchantment's `force` value to `level * 0.35`, run `/cve set knockback force level * 0.35`.
```
> cve set knockback force level * 0.35
Updated configuration for knockback->force
Outdated Value: level * 0.5
Updated Value: level * 0.35
```

Changes take place right away, a server restart is not required.
