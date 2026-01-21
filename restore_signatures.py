#!/usr/bin/env python3
"""
Restore GPG signatures from commit messages.

This script processes a git repository where GPG signatures have been stored
in commit messages (with the format "original_gpgsig <type>\n<data>") and
restores them as proper git commit signatures.

Usage:
    restore_signatures.py [--refs <refs>...]

If no refs are specified, processes all refs.
"""

import sys
import subprocess
import argparse
import re


def parse_data_block(stream):
    """Parse a 'data <size>' block from fast-export stream."""
    line = stream.readline()
    if not line.startswith(b'data '):
        raise ValueError(f"Expected 'data' line, got: {line}")

    size = int(line.split()[1])
    data = stream.read(size)

    # Consume trailing newline if present
    next_byte = stream.read(1)
    if next_byte and next_byte != b'\n':
        # Put it back by creating a new reader
        stream = type(stream)(next_byte + stream.read())

    return data


def extract_signature_from_message(commit_msg):
    """
    Extract GPG signature from commit message if present.

    Returns:
        tuple: (cleaned_message, sig_type, sig_data) or (commit_msg, None, None)
    """
    sig_identifier = b"\n\noriginal_gpgsig "
    sig_index = commit_msg.find(sig_identifier)

    if sig_index == -1:
        return commit_msg, None, None

    # Split message and signature section
    cleaned_msg = commit_msg[:sig_index]
    sig_section = commit_msg[sig_index + len(sig_identifier):]

    # Parse signature type and data
    first_newline = sig_section.find(b'\n')
    if first_newline == -1:
        # Malformed signature
        return commit_msg, None, None

    sig_type = sig_section[:first_newline]
    sig_data = sig_section[first_newline + 1:]

    return cleaned_msg, sig_type, sig_data


def process_fast_export_stream(input_stream, output_stream):
    """
    Process git fast-export stream, restoring signatures from messages.
    """
    commits_processed = 0
    signatures_restored = 0

    for line in input_stream:
        # Pass through non-commit lines
        if not line.startswith(b'commit '):
            output_stream.write(line)
            continue

        commits_processed += 1

        # Write commit line
        output_stream.write(line)

        # Collect commit headers
        headers = []
        commit_msg = None

        while True:
            line = input_stream.readline()

            if line.startswith(b'data '):
                # This is the commit message
                size = int(line.split()[1])
                commit_msg = input_stream.read(size)

                # Check for trailing newline
                next_line = input_stream.readline()
                break
            else:
                headers.append(line)

        # Extract signature if present
        cleaned_msg, sig_type, sig_data = extract_signature_from_message(commit_msg)

        # Write headers (mark, original-oid, author, committer, encoding)
        for header in headers:
            output_stream.write(header)

        # Write signature if we found one
        if sig_type and sig_data:
            signatures_restored += 1
            output_stream.write(b'gpgsig ' + sig_type + b'\n')
            output_stream.write(b'data %d\n' % len(sig_data))
            output_stream.write(sig_data)
            if not sig_data.endswith(b'\n'):
                output_stream.write(b'\n')

        # Write cleaned commit message
        output_stream.write(b'data %d\n' % len(cleaned_msg))
        output_stream.write(cleaned_msg)
        if not cleaned_msg.endswith(b'\n'):
            output_stream.write(b'\n')

        # Write the line after data block (might be 'from', 'merge', filemodify, or blank)
        output_stream.write(next_line)

        # Pass through rest of commit (file changes, etc.)
        while True:
            line = input_stream.readline()
            if not line:
                break

            output_stream.write(line)

            # Blank line signals end of commit
            if line == b'\n':
                break

    return commits_processed, signatures_restored


def restore_signatures(refs=None, verbose=False):
    """
    Restore GPG signatures from commit messages in the current repository.

    Args:
        refs: List of refs to process (default: all refs)
        verbose: Print progress information
    """
    if refs is None:
        refs = ['--all']

    if verbose:
        print(f"Exporting commits from refs: {' '.join(refs)}", file=sys.stderr)

    # Start git fast-export
    export_cmd = ['git', 'fast-export', '--show-original-ids'] + refs
    export_proc = subprocess.Popen(
        export_cmd,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )

    # Start git fast-import
    import_cmd = ['git', 'fast-import', '--force', '--quiet']
    import_proc = subprocess.Popen(
        import_cmd,
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )

    # Process the stream
    try:
        commits_processed, signatures_restored = process_fast_export_stream(
            export_proc.stdout,
            import_proc.stdin
        )

        # Close pipes
        import_proc.stdin.close()
        export_proc.stdout.close()

        # Wait for completion
        export_returncode = export_proc.wait()
        import_returncode = import_proc.wait()

        # Check for errors
        if export_returncode != 0:
            stderr = export_proc.stderr.read()
            raise RuntimeError(f"git fast-export failed: {stderr.decode()}")

        if import_returncode != 0:
            stderr = import_proc.stderr.read()
            raise RuntimeError(f"git fast-import failed: {stderr.decode()}")

        if verbose:
            print(f"Processed {commits_processed} commits", file=sys.stderr)
            print(f"Restored {signatures_restored} signatures", file=sys.stderr)

        return signatures_restored

    except Exception as e:
        # Clean up processes
        export_proc.kill()
        import_proc.kill()
        raise


def main():
    parser = argparse.ArgumentParser(
        description='Restore GPG signatures from commit messages',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
This script is designed to work after git-filter-repo has been used with
signatures stored in commit messages. It extracts signatures from messages
(in the format "original_gpgsig <type>\\n<data>") and restores them as
proper git commit signatures.

Example workflow:
  1. Run git-filter-repo with signatures in messages
  2. Run this script to restore the signatures

Note: This rewrites history and should only be used on repositories where
you control all clones.
"""
    )

    parser.add_argument(
        '--refs',
        nargs='+',
        help='Refs to process (default: --all)'
    )

    parser.add_argument(
        '-v', '--verbose',
        action='store_true',
        help='Print progress information'
    )

    args = parser.parse_args()

    try:
        signatures_restored = restore_signatures(
            refs=args.refs,
            verbose=args.verbose
        )

        if signatures_restored > 0:
            print(f"Successfully restored {signatures_restored} signature(s)")
            return 0
        else:
            if args.verbose:
                print("No signatures found to restore")
            return 0

    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
