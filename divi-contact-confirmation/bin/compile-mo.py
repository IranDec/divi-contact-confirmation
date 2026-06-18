#!/usr/bin/env python3
"""
Compile all .po files in ../languages/ into binary .mo files.

Usage:
    python bin/compile-mo.py

Run this script whenever you update a .po translation file.
Requires Python 3.6+ — no third-party packages needed.

Author: Mohammad Babaei <https://adschi.com>
"""

import struct
import os
import re
import sys


def parse_po(path):
    """Parse a .po file and return a list of (msgid, msgstr) pairs."""
    entries = []
    with open(path, encoding="utf-8") as f:
        content = f.read()

    # Split into blocks separated by blank lines
    blocks = re.split(r"\n{2,}", content.strip())

    for block in blocks:
        lines = block.strip().splitlines()

        msgid_lines = []
        msgstr_lines = []
        current = None

        for line in lines:
            if line.startswith("#"):
                continue
            elif line.startswith('msgid "'):
                current = "msgid"
                msgid_lines.append(line[7:-1])  # strip 'msgid "' and trailing '"'
            elif line.startswith('msgstr "'):
                current = "msgstr"
                msgstr_lines.append(line[8:-1])
            elif line.startswith('"') and line.endswith('"') and current:
                value = line[1:-1]
                if current == "msgid":
                    msgid_lines.append(value)
                else:
                    msgstr_lines.append(value)

        msgid  = unescape("".join(msgid_lines))
        msgstr = unescape("".join(msgstr_lines))

        # Skip the header entry (empty msgid) only if msgstr is also empty
        if msgid == "" and msgstr == "":
            continue

        entries.append((msgid, msgstr))

    return entries


def unescape(s):
    """Unescape standard gettext escape sequences."""
    return (
        s.replace("\\n", "\n")
         .replace("\\t", "\t")
         .replace("\\r", "\r")
         .replace('\\"', '"')
         .replace("\\\\", "\\")
    )


def build_mo(entries, path):
    """Write a binary .mo file from a list of (msgid, msgstr) pairs."""
    # Filter out entries with empty translations (keep the header)
    pairs = [(k.encode("utf-8"), v.encode("utf-8"))
             for k, v in entries if v != "" or k == ""]

    if not pairs:
        print(f"  ⚠  No translations found in entries — skipping {path}")
        return

    n = len(pairs)

    # Sort by original string (required by some MO readers)
    pairs.sort(key=lambda x: x[0])

    # MO layout:
    #   Header      : 7 × uint32 = 28 bytes
    #   Orig table  : n × 2 × uint32
    #   Trans table : n × 2 × uint32
    #   String data

    header_size  = 28
    table_size   = n * 8  # n pairs × 2 uint32s × 4 bytes
    strings_base = header_size + 2 * table_size

    orig_offsets  = []
    trans_offsets = []
    string_data   = bytearray()

    for orig, trans in pairs:
        orig_offsets.append((len(orig), strings_base + len(string_data)))
        string_data += orig + b"\x00"

    trans_base = strings_base + len(string_data)
    # Reset so we can compute translation offsets
    string_data_trans = bytearray()
    for orig, trans in pairs:
        trans_offsets.append((len(trans), trans_base + len(string_data_trans)))
        string_data_trans += trans + b"\x00"

    mo = bytearray()

    # Magic (little-endian) + revision 0
    mo += struct.pack("<I", 0x950412DE)  # magic
    mo += struct.pack("<I", 0)           # revision
    mo += struct.pack("<I", n)           # number of strings
    mo += struct.pack("<I", header_size) # offset of orig string table
    mo += struct.pack("<I", header_size + table_size)  # offset of trans string table
    mo += struct.pack("<I", 0)           # hash table size (we skip it)
    mo += struct.pack("<I", header_size + 2 * table_size)  # offset of hash table

    for length, offset in orig_offsets:
        mo += struct.pack("<II", length, offset)

    for length, offset in trans_offsets:
        mo += struct.pack("<II", length, offset)

    mo += string_data
    mo += string_data_trans

    with open(path, "wb") as f:
        f.write(mo)

    print(f"  ✓  Written {path}  ({n} strings, {len(mo)} bytes)")


def main():
    script_dir = os.path.dirname(os.path.abspath(__file__))
    lang_dir   = os.path.normpath(os.path.join(script_dir, "..", "languages"))

    po_files = [f for f in os.listdir(lang_dir) if f.endswith(".po")]

    if not po_files:
        print("No .po files found in", lang_dir)
        sys.exit(0)

    print(f"Compiling {len(po_files)} .po file(s) in {lang_dir}\n")

    for po_file in sorted(po_files):
        po_path = os.path.join(lang_dir, po_file)
        mo_path = po_path[:-3] + ".mo"
        print(f"  Parsing {po_file} …")
        try:
            entries = parse_po(po_path)
            build_mo(entries, mo_path)
        except Exception as e:
            print(f"  ✗  Error processing {po_file}: {e}")

    print("\nDone.")


if __name__ == "__main__":
    main()
