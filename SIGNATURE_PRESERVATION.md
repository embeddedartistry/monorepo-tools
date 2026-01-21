# GPG Signature Preservation for Git Filter Operations

This directory contains Python scripts to preserve GPG commit signatures when using `git-filter-repo` or other history-rewriting operations with standard git tools.

## Problem

When rewriting git history (e.g., with `git-filter-repo`), GPG signatures become invalid because they sign the exact commit hash. However, standard tools don't provide a way to preserve signatures through the filtering process.

## Solution

We use a two-step approach:

1. **Before filtering**: Move signatures from commits into commit messages
2. **After filtering**: Restore signatures from messages back to commits

## Scripts

### `store_signatures.py`
Moves GPG signatures from commits into commit messages.

**Usage:**
```bash
./store_signatures.py [--refs <refs>...] [-v]
```

**Example:**
```bash
# Store all signatures
./store_signatures.py --refs main

# Verbose mode
./store_signatures.py -v
```

### `restore_signatures.py`
Restores GPG signatures from commit messages back to commits.

**Usage:**
```bash
./restore_signatures.py [--refs <refs>...] [-v]
```

**Example:**
```bash
# Restore all signatures
./restore_signatures.py --refs main

# Verbose mode
./restore_signatures.py -v
```

## Complete Workflow

### For monorepo splitting with signature preservation:

```bash
# 1. Store signatures in commit messages
cd /path/to/monorepo
./tools/monorepo-tools/store_signatures.py -v

# 2. Run git-filter-repo as normal (signatures are now preserved in messages)
git-filter-repo --subdirectory-filter packages/my-package --force

# 3. Restore signatures from messages back to commits
./tools/monorepo-tools/restore_signatures.py -v

# 4. Verify
git log --show-signature
```

### For use with monorepo_split.sh:

The `monorepo_split.sh` script can be enhanced to call these scripts automatically:

```bash
# Before running rewrite_history_from.sh:
./tools/monorepo-tools/store_signatures.py --refs main $(git tag -l)

# After running rewrite_history_from.sh:
./tools/monorepo-tools/restore_signatures.py --refs main $(git tag -l)
```

## Requirements

- Python 3.6+
- Git 2.34+ (for `--signed-commits=verbatim` support in fast-export)
- Standard git tools (no patches required)

## How It Works

1. **Storage Phase** (`store_signatures.py`):
   - Uses `git fast-export --signed-commits=verbatim` to export commits with signatures
   - Parses the export stream and extracts `gpgsig` blocks
   - Appends signature data to commit messages in the format:
     ```
     original_gpgsig <type>
     <signature data>
     ```
   - Re-imports commits with `git fast-import`

2. **Restoration Phase** (`restore_signatures.py`):
   - Uses `git fast-export` to export filtered commits
   - Searches commit messages for `original_gpgsig` markers
   - Extracts signature data from messages
   - Writes signatures as proper `gpgsig` blocks
   - Cleans commit messages (removes signature markers)
   - Re-imports commits with `git fast-import`

## Format Details

Signatures are stored in commit messages using this format:

```
<original commit message>

original_gpgsig sha1 openpgp
-----BEGIN PGP SIGNATURE-----
<signature data>
-----END PGP SIGNATURE-----
```

The format matches git's fast-import/export signature format:
- `sha1` or `sha256`: The git hash algorithm used
- `openpgp`, `x509`, `ssh`, etc.: The signature format

## Notes

- These scripts rewrite history and will change commit hashes
- Only use on repositories where you control all clones
- Signatures may become invalid after filtering (they'll still be preserved, but won't verify)
- The scripts are idempotent - running them multiple times is safe
- Original signatures are preserved byte-for-byte

## Testing

To verify the scripts work:

```bash
# Create a test commit with an embedded signature
cd /tmp
git init test-repo
cd test-repo
echo "test" > file.txt
git add file.txt
git commit -m "Test

original_gpgsig sha1 openpgp
-----BEGIN PGP SIGNATURE-----
test data
-----END PGP SIGNATURE-----"

# Restore the signature
/path/to/restore_signatures.py -v

# Verify it was restored
git cat-file commit HEAD
```

## Troubleshooting

**"git fast-export failed"**
- Ensure you have Git 2.34+ with `--signed-commits` support
- Check that refs exist: `git log --all`

**"No signatures found"**
- For `store_signatures.py`: Repository may not have signed commits
- For `restore_signatures.py`: Check commit messages contain `original_gpgsig` markers

**Signatures don't verify after restoration**
- This is expected! Signatures were created for the original commits
- The signatures are preserved for historical/audit purposes
- To create valid signatures, you'd need to re-sign commits with your key
