#!/usr/bin/env python3
"""
Fix portal.php by removing orphaned duplicate code after line 1695
"""

import os
import shutil
from datetime import datetime

# File paths
file_path = 'portal.php'
backup_path = f'portal_backup_{datetime.now().strftime("%Y%m%d_%H%M%S")}.php'

# Create backup
shutil.copy(file_path, backup_path)
print(f"✅ Backup created: {backup_path}")

# Read the file
with open(file_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

print(f"📄 Total lines in original file: {len(lines)}")

# Keep only the first 1695 lines
fixed_lines = lines[:1695]

print(f"✂️  Lines after fix: {len(fixed_lines)}")

# Write the fixed content back
with open(file_path, 'w', encoding='utf-8') as f:
    f.writelines(fixed_lines)

print(f"\n✅ SUCCESS!")
print(f"portal.php has been fixed!")
print(f"Removed {len(lines) - len(fixed_lines)} orphaned lines.")
print(f"\n🔄 Please refresh your portal page and test the modal.")

