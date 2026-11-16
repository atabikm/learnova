#!/bin/sh
set -e

# Usage: ./scripts/create-plugin.sh <type> <name> [display_name]
# Example: ./scripts/create-plugin.sh local myreports "My Reports"
# Example: ./scripts/create-plugin.sh block dashboard "Dashboard"
# Example: ./scripts/create-plugin.sh mod quiz "Quiz"
# Example: ./scripts/create-plugin.sh theme mytheme "My Theme"

if [ -z "$1" ] || [ -z "$2" ]; then
    echo "Usage: $0 <type> <name> [display_name]"
    echo "  type: local, block, mod, theme"
    echo "  name: short name (e.g. myreports, dashboard)"
    echo "  display_name: human readable name (e.g. 'My Reports')"
    exit 1
fi

TYPE="$1"
NAME="$2"
DISPLAY="${3:-$(echo "$NAME" | sed 's/_/ /g; s/\b\(.\)/\U\1/g')}"
COMPANY="learnova"
COMPONENT="${TYPE}_${COMPANY}${NAME}"

DIR=""
case "$TYPE" in
    local)  DIR="local/${COMPANY}${NAME}" ;;
    block)  DIR="blocks/${COMPANY}${NAME}" ;;
    mod)    DIR="mod/${COMPANY}${NAME}" ;;
    theme)  DIR="theme/${COMPANY}${NAME}" ;;
    *)      echo "Unknown type: $TYPE"; exit 1 ;;
esac

if [ -d "$DIR" ]; then
    echo "Error: Plugin directory '$DIR' already exists"
    exit 1
fi

mkdir -p "$DIR"

cat > "$DIR/version.php" <<EOF
<?php
defined('MOODLE_INTERNAL') || die();

\$plugin->component = '$COMPONENT';
\$plugin->version = 2025010100;
\$plugin->requires = 2024100700;
\$plugin->maturity = MATURITY_ALPHA;
\$plugin->release = '0.1.0';
EOF

cat > "$DIR/README.md" <<EOF
# $DISPLAY

Plugin type: $TYPE
Component: $COMPONENT

## Development

This plugin is developed within the Learnova LMS Web repository.

## Installation

Installed automatically when using docker compose.
EOF

echo "Created plugin: $DIR (component: $COMPONENT)"
echo "Next: Edit $DIR/version.php and add your plugin code"
