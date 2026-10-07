#!/usr/bin/env python3
"""
Construit un ZIP d'installation du plugin WAW Nav Accordion.

Lit le fichier `.distignore` pour exclure les fichiers/dossiers de dev,
et place le contenu dans un dossier `wawp-nav-accordion/` à la racine du ZIP
(convention WordPress : un site déposera ce zip via Extensions › Ajouter).

Usage :
    python build-zip.py             # build par défaut : dist/wawp-nav-accordion-{version}.zip
    python build-zip.py --out chemin.zip
    python build-zip.py --no-version
    python build-zip.py --dry-run   # liste les fichiers sans créer le zip
"""

from __future__ import annotations

import argparse
import fnmatch
import re
import sys
import zipfile
from pathlib import Path

PLUGIN_SLUG = "wawp-nav-accordion"
PLUGIN_FILE = "wawp-nav-accordion.php"
DISTIGNORE = ".distignore"
DEFAULT_OUT_DIR = "dist"


def read_distignore(root: Path) -> tuple[set[str], list[str]]:
    """Lit `.distignore` et retourne (dossiers_exclus, motifs_fichiers).

    Les entrées finissant par `/` sont des dossiers (match sur le chemin entier).
    Les autres sont des motifs glob appliqués au nom de fichier OU au chemin relatif.
    """
    path = root / DISTIGNORE
    if not path.exists():
        return set(), []

    dirs: set[str] = set()
    files: list[str] = []

    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if line.endswith("/"):
            dirs.add(line.rstrip("/"))
        else:
            files.append(line)

    return dirs, files


def get_plugin_version(root: Path) -> str:
    """Extrait la version depuis l'en-tête de wawp-nav-accordion.php."""
    php = root / PLUGIN_FILE
    if not php.exists():
        return "0.0.0"
    text = php.read_text(encoding="utf-8")
    m = re.search(r"^\s*\*\s*Version:\s*([^\s*]+)", text, re.MULTILINE)
    return m.group(1) if m else "0.0.0"


def is_excluded(
    rel: Path, excluded_dirs: set[str], excluded_files: list[str]
) -> bool:
    """Détermine si un chemin relatif doit être exclu."""
    parts = rel.parts

    # Dossier exclu : si l'un des segments correspond à un dossier exclu.
    for d in excluded_dirs:
        # Match exact d'un segment ou chemin relatif commençant par le dossier.
        if d in parts:
            return True
        if str(rel).replace("\\", "/").startswith(d + "/"):
            return True

    # Motifs fichiers : on teste sur le nom, le chemin POSIX et chaque segment.
    rel_posix = str(rel).replace("\\", "/")
    name = rel.name
    for pat in excluded_files:
        if fnmatch.fnmatch(name, pat):
            return True
        if fnmatch.fnmatch(rel_posix, pat):
            return True
        # Permet à `composer.json` (sans /) de matcher uniquement à la racine —
        # déjà couvert par le test sur `name` mais reste cohérent.

    return False


def collect_files(
    root: Path, excluded_dirs: set[str], excluded_files: list[str]
) -> list[Path]:
    """Parcours récursif filtré."""
    kept: list[Path] = []

    for path in sorted(root.rglob("*")):
        if path.is_dir():
            # Pas besoin de listing du dossier ; rglob va dedans.
            continue
        rel = path.relative_to(root)
        if is_excluded(rel, excluded_dirs, excluded_files):
            continue
        # Exclut aussi le script lui-même et le dossier de sortie par défaut.
        if rel.parts and rel.parts[0] == DEFAULT_OUT_DIR:
            continue
        if rel.name == Path(__file__).name and rel.parent == Path("."):
            continue
        kept.append(rel)

    return kept


def build_zip(root: Path, files: list[Path], out: Path) -> None:
    out.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(
        out, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9
    ) as zf:
        for rel in files:
            arcname = f"{PLUGIN_SLUG}/{rel.as_posix()}"
            zf.write(root / rel, arcname)


def human_size(n: int) -> str:
    for unit in ("o", "Kio", "Mio"):
        if n < 1024:
            return f"{n:.1f} {unit}"
        n /= 1024
    return f"{n:.1f} Gio"


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Construit le ZIP d'installation du plugin WAW Nav Accordion."
    )
    parser.add_argument(
        "--out",
        type=Path,
        default=None,
        help="Chemin de sortie du zip (par défaut : dist/wawp-nav-accordion-{version}.zip).",
    )
    parser.add_argument(
        "--no-version",
        action="store_true",
        help="Ne pas inclure la version dans le nom du fichier.",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Liste les fichiers qui seraient inclus, sans créer le zip.",
    )
    args = parser.parse_args()

    root = Path(__file__).resolve().parent

    if not (root / PLUGIN_FILE).exists():
        print(
            f"[ERREUR] {PLUGIN_FILE} introuvable dans {root}. "
            "Lancez ce script depuis la racine du plugin.",
            file=sys.stderr,
        )
        return 1

    excluded_dirs, excluded_files = read_distignore(root)
    files = collect_files(root, excluded_dirs, excluded_files)

    version = get_plugin_version(root)
    if args.out is not None:
        out_path = args.out
    elif args.no_version:
        out_path = root / DEFAULT_OUT_DIR / f"{PLUGIN_SLUG}.zip"
    else:
        out_path = root / DEFAULT_OUT_DIR / f"{PLUGIN_SLUG}-{version}.zip"

    total_size = sum((root / f).stat().st_size for f in files)
    print(
        f"Plugin   : {PLUGIN_SLUG} v{version}\n"
        f"Fichiers : {len(files)} ({human_size(total_size)} non compressés)\n"
        f"Sortie   : {out_path}"
    )

    if args.dry_run:
        print("\n--- Liste des fichiers (dry-run) ---")
        for f in files:
            print(f"  {PLUGIN_SLUG}/{f.as_posix()}")
        return 0

    build_zip(root, files, out_path)

    final_size = out_path.stat().st_size
    print(f"\n[OK] Zip créé : {out_path} ({human_size(final_size)} compressé)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
