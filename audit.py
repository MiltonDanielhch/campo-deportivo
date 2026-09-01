#!/usr/bin/env python3
"""
Project Auditor v2.3 — Refactored
====================================
Auditoría de proyectos con métricas detalladas, CLI completa,
manejo de symlinks, lectura de .gitignore, y análisis por capas.

Mejoras v2.3:
- Detección automática de proyectos Flutter.
- Carpetas ios/ y android/ en Flutter se tratan como código generado (resumen).
- Resumen inteligente de dependencias (vendor, node_modules, etc.).

Uso:
    python audit.py
    python audit.py /path/to/project --output report.md --depth 2
    python audit.py . --json --no-gitignore --skip-empty
"""

from __future__ import annotations

import argparse
import fnmatch
import json
import os
import sys
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Iterator

# ── Optional dependencies ──────────────────────────────────────────────────────
try:
    from tqdm import tqdm
except ImportError:
    tqdm = None  # type: ignore[misc,assignment]

# ── Constants ──────────────────────────────────────────────────────────────────
# Carpetas que se ignoran por completo (no aportan a métricas ni al árbol)
DEFAULT_IGNORE = {
    ".git",
    ".gitignore",
    ".dockerignore",
    ".DS_Store",
    ".vscode",
    ".idea",
    ".astro",
    "__pycache__",
    ".venv",
    ".env",
    "backend.db",
    "backend.db-shm",
    "backend.db-wal",
    "uploads",
    "archive",
    "test-results",
    "playwright-report",
    ".next",
    ".nuxt",
    ".svelte-kit",
}

# Carpetas de dependencias/artefactos que se resumen en una sola línea
DEPENDENCY_DIRS = {
    "vendor",       # PHP/Composer, Go
    "node_modules", # Node.js / Deno / Bun
    ".dart_tool",   # Flutter / Dart
    "Pods",         # iOS (CocoaPods)
    "deps",         # Elixir (Mix)
    "_build",       # Elixir (Mix)
    ".gradle",      # Android / Gradle
    ".m2",          # Maven
    "build",        # Java, Flutter, JS build outputs
    "dist",         # JS / Python build outputs
    "target",       # Rust / Java (Maven)
    "bin",          # .NET
    "obj",          # .NET
    "coverage",     # Test coverage reports
    ".pytest_cache",
}

# Carpetas que son código generado SOLO en proyectos Flutter
FLUTTER_GENERATED_DIRS = {"ios", "android"}

COMMENT_PATTERNS = {
    ".py": ("#", "/*"),
    ".js": ("//", "/*"),
    ".mjs": ("//", "/*"),
    ".ts": ("//", "/*"),
    ".tsx": ("//", "/*"),
    ".rs": ("//", "/*"),
    ".sql": ("--", "/*"),
    ".css": (None, "/*"),
    ".scss": ("//", "/*"),
    ".html": (None, "<!--"),
    ".htm": (None, "<!--"),
    ".astro": (None, "<!--"),
    ".svelte": (None, "<!--"),
    ".md": (None, "<!--"),
    ".yml": ("#", None),
    ".yaml": ("#", None),
    ".toml": ("#", None),
}


# ── Data classes ────────────────────────────────────────────────────────────
@dataclass
class FileMetrics:
    path: Path
    lines_total: int = 0
    lines_blank: int = 0
    lines_comment: int = 0
    lines_code: int = 0
    size: int = 0

    @property
    def ext(self) -> str:
        return self.path.suffix.lower()


@dataclass
class DirMetrics:
    path: Path
    files: list[FileMetrics] = field(default_factory=list)
    subdirs: list[DirMetrics] = field(default_factory=list)
    _cached_lines: int | None = field(default=None, repr=False)
    _cached_size: int | None = field(default=None, repr=False)

    @property
    def total_lines(self) -> int:
        if self._cached_lines is None:
            self._cached_lines = sum(f.lines_code for f in self.files) + sum(
                d.total_lines for d in self.subdirs
            )
        return self._cached_lines

    @property
    def total_size(self) -> int:
        if self._cached_size is None:
            self._cached_size = sum(f.size for f in self.files) + sum(
                d.total_size for d in self.subdirs
            )
        return self._cached_size

    @property
    def file_count(self) -> int:
        return len(self.files) + sum(d.file_count for d in self.subdirs)

    @property
    def total_comments(self) -> int:
        return sum(f.lines_comment for f in self.files) + sum(
            d.total_comments for d in self.subdirs
        )


# ── Helpers ─────────────────────────────────────────────────────────────────
def human_size(b: int, factor: int = 1024, suffix: str = "B") -> str:
    """Convierte bytes a formato legible."""
    for unit in ("", "K", "M", "G", "T", "P"):
        if abs(b) < factor:
            return f"{b:.2f}{unit}{suffix}"
        b /= factor
    return f"{b:.2f}E{suffix}"


def summarize_dependency(directory: Path) -> tuple[int, int]:
    """Cuenta archivos y tamaño total de un directorio de dependencias sin analizar contenido."""
    file_count = 0
    total_size = 0
    try:
        for entry in directory.rglob('*'):
            if entry.is_file() and not entry.is_symlink():
                file_count += 1
                try:
                    total_size += entry.stat().st_size
                except OSError:
                    pass
    except (OSError, PermissionError):
        pass
    return file_count, total_size


def is_flutter_project(directory: Path) -> bool:
    """Detecta si un directorio es un proyecto Flutter."""
    return (directory / "pubspec.yaml").exists()


def is_binary(file_path: Path, sample_size: int = 8192) -> bool:
    """Heurística para detectar archivos binarios."""
    try:
        with open(file_path, "rb") as f:
            chunk = f.read(sample_size)
    except OSError:
        return True
    if not chunk:
        return False
    if b"\x00" in chunk:
        return True
    text_chars = bytearray({7, 8, 9, 10, 12, 13, 27} | set(range(0x20, 0x100)) - {0x7F})
    non_text = chunk.translate(None, text_chars)
    return bool(non_text) and len(non_text) / len(chunk) > 0.30


def parse_gitignore(root: Path) -> set[str]:
    """Lee .gitignore y devuelve patrones simples."""
    gitignore = root / ".gitignore"
    patterns: set[str] = set()
    if not gitignore.exists():
        return patterns
    try:
        with open(gitignore, "r", encoding="utf-8", errors="ignore") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                if line.startswith("!"):
                    continue
                patterns.add(line.rstrip("/"))
    except OSError:
        pass
    return patterns


def matches_any(name: str, patterns: set[str]) -> bool:
    """Comprueba si un nombre coincide con algún patrón (simple o glob)."""
    for pat in patterns:
        if fnmatch.fnmatch(name, pat) or name == pat:
            return True
    return False


def count_lines_advanced(file_path: Path) -> FileMetrics:
    """Cuenta líneas totales, en blanco, comentarios y código."""
    fm = FileMetrics(path=file_path, size=file_path.stat().st_size)

    if is_binary(file_path):
        return fm

    ext = fm.ext
    line_comment, block_comment = COMMENT_PATTERNS.get(ext, (None, None))

    in_block = False
    block_end = None
    if block_comment == "/*":
        block_end = "*/"
    elif block_comment == "<!--":
        block_end = "-->"

    try:
        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            for raw_line in f:
                line = raw_line.rstrip("\n\r")
                fm.lines_total += 1

                stripped = line.strip()
                if not stripped:
                    fm.lines_blank += 1
                    continue

                if in_block:
                    fm.lines_comment += 1
                    if block_end and block_end in stripped:
                        in_block = False
                    continue

                if block_end and block_comment and block_comment in stripped:
                    start_idx = stripped.find(block_comment)
                    end_idx = stripped.find(block_end, start_idx + len(block_comment))
                    if end_idx != -1:
                        before = stripped[:start_idx].strip()
                        if before:
                            fm.lines_code += 1
                        else:
                            fm.lines_comment += 1
                        continue
                    else:
                        fm.lines_comment += 1
                        in_block = True
                        continue

                if line_comment and stripped.startswith(line_comment):
                    fm.lines_comment += 1
                    continue

                fm.lines_code += 1
    except (OSError, UnicodeDecodeError):
        pass

    return fm


# ── Core analysis ───────────────────────────────────────────────────────────
def should_ignore(
    path: Path, root: Path, ignore_names: set[str], gitignore: set[str]
) -> bool:
    """Decide si un path debe ser ignorado."""
    name = path.name
    if name in ignore_names:
        return True
    if matches_any(name, gitignore):
        return True
    return False


def analyze_directory(
    directory: Path,
    root: Path,
    ignore_names: set[str],
    gitignore: set[str],
    prefix: str = "",
    progress: tqdm | None = None,
    skip_empty: bool = False,
) -> tuple[str, DirMetrics]:
    """Recorre recursivamente y genera árbol + métricas."""
    tree_lines: list[str] = []
    dm = DirMetrics(path=directory)

    # 🆕 Detectar si este directorio es un proyecto Flutter
    is_flutter = is_flutter_project(directory)

    try:
        entries = sorted(
            [
                e
                for e in directory.iterdir()
                if not should_ignore(e, root, ignore_names, gitignore)
            ],
            key=lambda e: (e.is_dir(), e.name.lower()),
        )
    except PermissionError:
        tree_lines.append(f"{prefix}[Acceso Denegado]\n")
        return "\n".join(tree_lines), dm
    except OSError:
        return "", dm

    for idx, entry in enumerate(entries):
        is_last = idx == len(entries) - 1
        connector = "└── " if is_last else "├── "
        child_prefix = prefix + ("    " if is_last else "│   ")

        if entry.is_symlink():
            target = os.readlink(entry)
            tree_lines.append(
                f"{prefix}{connector}{entry.name} -> {target} (symlink)\n"
            )
            continue

        # 🆕 Manejo de carpetas de dependencias (Resumen rápido)
        is_dependency = entry.name in DEPENDENCY_DIRS

        # 🆕 Si es proyecto Flutter, tratar ios/ y android/ como código generado
        if is_flutter and entry.name in FLUTTER_GENERATED_DIRS:
            is_dependency = True

        if entry.is_dir() and is_dependency:
            file_count, size = summarize_dependency(entry)

            # Creamos un DirMetrics sintético para que el tamaño sume al total del proyecto
            # pero sin aportar líneas de código (LoC) ni ensuciar las tablas de extensiones.
            sub_dm = DirMetrics(path=entry)
            sub_dm._cached_size = size

            dm.subdirs.append(sub_dm)

            # Mensaje diferente para Flutter
            if is_flutter and entry.name in FLUTTER_GENERATED_DIRS:
                tree_lines.append(
                    f"{prefix}{connector}{entry.name}/ [{human_size(size)} | {file_count} archivos | código nativo generado]\n"
                )
            else:
                tree_lines.append(
                    f"{prefix}{connector}{entry.name}/ [{human_size(size)} | {file_count} archivos | dependencias omitidas]\n"
                )
            continue

        if entry.is_dir():
            subtree, sub_dm = analyze_directory(
                entry, root, ignore_names, gitignore, child_prefix, progress, skip_empty
            )
            dm.subdirs.append(sub_dm)
            tree_lines.append(
                f"{prefix}{connector}{entry.name}/ [{human_size(sub_dm.total_size)}]\n"
            )
            tree_lines.append(subtree)
        else:
            fm = count_lines_advanced(entry)
            if skip_empty and fm.size == 0 and fm.lines_code == 0:
                continue
            dm.files.append(fm)
            if progress:
                progress.update(1)
            info = f"({fm.lines_code} LoC | {human_size(fm.size)})"
            if fm.lines_comment:
                info += f" [{fm.lines_comment} comentarios]"
            tree_lines.append(f"{prefix}{connector}{entry.name} {info}\n")

    return "".join(tree_lines), dm


# ── Reporting ───────────────────────────────────────────────────────────────
def build_layer_table(
    dm: DirMetrics,
    total_lines: int,
    total_size: int,
    depth: int = 1,
    show_comments: bool = True,
) -> str:
    """Tabla de desglose por capas (hasta cierta profundidad)."""
    lines_out: list[str] = []

    if show_comments:
        lines_out.append("| Capa | Archivos | LoC | Comentarios | Peso | % LoC |")
        lines_out.append("| :--- | ---: | ---: | ---: | ---: | ---: |")
    else:
        lines_out.append("| Capa | Archivos | LoC | Peso | % LoC |")
        lines_out.append("| :--- | ---: | ---: | ---: | ---: |")

    def collect_layers(
        current: DirMetrics, current_depth: int
    ) -> Iterator[tuple[str, int, int, int, int]]:
        if current_depth > depth:
            return
        file_count = len(current.files)
        loc = sum(f.lines_code for f in current.files)
        comments = sum(f.lines_comment for f in current.files)
        size = sum(f.size for f in current.files)

        if current_depth >= 1 and (loc > 0 or file_count > 0):
            rel_name = current.path.name or "."
            yield (rel_name, file_count, loc, comments, size)

        # 🆕 Detectar si el directorio actual es un proyecto Flutter
        is_flutter_parent = is_flutter_project(current.path)

        for sub in current.subdirs:
            if current_depth < depth:
                yield from collect_layers(sub, current_depth + 1)
            else:
                # Filtramos subdirectorios sintéticos de dependencias para no mostrarlos como "Capas"
                if sub.path.name in DEPENDENCY_DIRS:
                    continue

                # 🆕 Si el padre es Flutter, también filtrar ios/ y android/
                if is_flutter_parent and sub.path.name in FLUTTER_GENERATED_DIRS:
                    continue

                yield (sub.path.name, sub.file_count, sub.total_lines, 0, sub.total_size)

    layers = list(collect_layers(dm, 0))
    layers.sort(key=lambda x: x[2], reverse=True)

    for name, files, loc, comments, size in layers:
        pct = (loc / total_lines * 100) if total_lines > 0 else 0
        bar = "█" * int(pct / 5)
        if show_comments:
            lines_out.append(
                f"| `{name}` | {files} | {loc} | {comments} | {human_size(size)} | {pct:.1f}% {bar} |"
            )
        else:
            lines_out.append(
                f"| `{name}` | {files} | {loc} | {human_size(size)} | {pct:.1f}% {bar} |"
            )

    if show_comments:
        lines_out.append(
            f"| **TOTAL** | — | **{total_lines}** | — | **{human_size(total_size)}** | 100% |"
        )
    else:
        lines_out.append(
            f"| **TOTAL** | — | **{total_lines}** | **{human_size(total_size)}** | 100% |"
        )
    return "\n".join(lines_out)


def build_extension_table(dm: DirMetrics) -> str:
    """Tabla de extensiones más usadas."""
    stats: dict[str, dict[str, int]] = {}

    def walk(d: DirMetrics) -> None:
        for f in d.files:
            ext = f.ext or "(sin extensión)"
            if ext not in stats:
                stats[ext] = {"files": 0, "loc": 0, "size": 0}
            stats[ext]["files"] += 1
            stats[ext]["loc"] += f.lines_code
            stats[ext]["size"] += f.size
        for sub in d.subdirs:
            walk(sub)

    walk(dm)
    if not stats:
        return ""

    lines_out = [
        "## Desglose por Extensión\n",
        "| Extensión | Archivos | LoC | Peso |",
        "| :--- | ---: | ---: | ---: |",
    ]
    for ext, data in sorted(stats.items(), key=lambda x: x[1]["loc"], reverse=True):
        lines_out.append(
            f"| `{ext}` | {data['files']} | {data['loc']} | {human_size(data['size'])} |"
        )
    return "\n".join(lines_out)


def build_largest_files(dm: DirMetrics, root: Path, top_n: int = 10) -> str:
    """Lista los archivos más grandes por LoC."""
    all_files: list[FileMetrics] = []

    def walk(d: DirMetrics) -> None:
        all_files.extend(d.files)
        for sub in d.subdirs:
            walk(sub)

    walk(dm)
    top = sorted(all_files, key=lambda f: f.lines_code, reverse=True)[:top_n]
    if not top:
        return ""

    has_comments = any(f.lines_comment > 0 for f in top)

    if has_comments:
        lines_out = [
            f"## Top {top_n} Archivos por LoC\n",
            "| Archivo | LoC | Comentarios | En blanco | Peso |",
            "| :--- | ---: | ---: | ---: | ---: |",
        ]
        for f in top:
            rel = f.path.relative_to(root).as_posix()
            lines_out.append(
                f"| `{rel}` | {f.lines_code} | {f.lines_comment} | {f.lines_blank} | {human_size(f.size)} |"
            )
    else:
        lines_out = [
            f"## Top {top_n} Archivos por LoC\n",
            "| Archivo | LoC | En blanco | Peso |",
            "| :--- | ---: | ---: | ---: |",
        ]
        for f in top:
            rel = f.path.relative_to(root).as_posix()
            lines_out.append(
                f"| `{rel}` | {f.lines_code} | {f.lines_blank} | {human_size(f.size)} |"
            )
    return "\n".join(lines_out)


# ── Main ──────────────────────────────────────────────────────────────────────
def generate_audit(args: argparse.Namespace) -> None:
    root = Path(args.root).resolve()
    if not root.is_dir():
        print(f"❌ Error: {root} no es un directorio válido.", file=sys.stderr)
        sys.exit(1)

    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M")
    root_name = root.name

    ignore_names = set(DEFAULT_IGNORE)
    if args.ignore:
        ignore_names.update(args.ignore.split(","))

    gitignore: set[str] = set()
    if not args.no_gitignore:
        gitignore = parse_gitignore(root)

    # Conteo preciso de archivos para la barra de progreso (omitiendo dependencias)
    total_files = 0
    if tqdm:
        for root_dir, dirs, files in os.walk(root):
            # Verificar si estamos en un proyecto Flutter
            is_flutter = is_flutter_project(Path(root_dir))

            # Filtrar directorios
            filtered_dirs = []
            for d in dirs:
                if d in ignore_names:
                    continue
                if d in DEPENDENCY_DIRS:
                    continue
                # Si es Flutter, omitir ios/ y android/
                if is_flutter and d in FLUTTER_GENERATED_DIRS:
                    continue
                filtered_dirs.append(d)

            dirs[:] = filtered_dirs

            # Filtrar archivos
            filtered_files = [f for f in files if f not in ignore_names]
            total_files += len(filtered_files)

    progress = tqdm(total=total_files, desc="Analizando", unit="arch") if tqdm else None

    tree_content, dm = analyze_directory(
        root, root, ignore_names, gitignore, progress=progress, skip_empty=args.skip_empty
    )

    if progress:
        progress.close()

    total_lines = dm.total_lines
    total_size = dm.total_size
    total_comments = dm.total_comments

    show_comments = total_comments > 0

    layer_table = build_layer_table(
        dm, total_lines, total_size, depth=args.depth, show_comments=show_comments
    )
    ext_table = build_extension_table(dm)
    top_files = build_largest_files(dm, root, top_n=args.top)

    report_parts = [
        f"# 🛠️ Auditoría de Software — {root_name}\n",
        f"> Generado: `{timestamp}`\n",
        "## Resumen\n",
        "| Métrica | Valor |",
        "| :--- | :--- |",
        f"| **Proyecto** | `{root_name}` |",
        f"| **Líneas de Código (Netas)** | {total_lines} LoC |",
        f"| **Peso Total del Proyecto** | {human_size(total_size)} |",
        f"| **Archivos analizados** | {dm.file_count} |",
        f"| **Timestamp** | {timestamp} |",
        f"| **Estado** | Activa |",
        "",
        "## Breakdown por Capa\n",
        layer_table,
    ]

    if ext_table:
        report_parts += ["", ext_table]
    if top_files:
        report_parts += ["", top_files]

    report_parts += [
        "",
        "## Mapa de Arquitectura\n",
        "```text",
        f"{root_name}/",
        tree_content.rstrip(),
        "```",
    ]

    report = "\n".join(report_parts) + "\n"

    if args.json:
        data = {
            "project": root_name,
            "timestamp": timestamp,
            "total_lines": total_lines,
            "total_size": total_size,
            "total_files": dm.file_count,
            "tree": tree_content,
        }
        report = json.dumps(data, indent=2, ensure_ascii=False)

    output_path = Path(args.output)
    with open(output_path, "w", encoding="utf-8") as f:
        f.write(report)

    print(f"✅ Auditoría completada [{timestamp}]")
    print(f"   Proyecto  : {root_name}/")
    print(f"   Total LoC : {total_lines}")
    print(f"   Peso      : {human_size(total_size)}")
    print(f"   Archivos  : {dm.file_count}")
    print(f"   Reporte   : {output_path}")


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Auditoría de proyectos con métricas detalladas."
    )
    parser.add_argument("root", nargs="?", default=".", help="Directorio raíz a auditar")
    parser.add_argument(
        "-o", "--output", default="AUDITORIA_MASTER.md", help="Archivo de salida"
    )
    parser.add_argument(
        "-d", "--depth", type=int, default=1, help="Profundidad del breakdown (default: 1)"
    )
    parser.add_argument(
        "--ignore", default="", help="Nombres extra a ignorar, separados por coma"
    )
    parser.add_argument(
        "--no-gitignore", action="store_true", help="No leer .gitignore"
    )
    parser.add_argument(
        "--json", action="store_true", help="Exportar como JSON en vez de Markdown"
    )
    parser.add_argument(
        "--top", type=int, default=10, help="Cantidad de archivos top a mostrar"
    )
    parser.add_argument(
        "--skip-empty",
        action="store_true",
        help="Omitir archivos vacíos (0 bytes, 0 LoC) del árbol",
    )
    args = parser.parse_args()
    generate_audit(args)


if __name__ == "__main__":
    main()
