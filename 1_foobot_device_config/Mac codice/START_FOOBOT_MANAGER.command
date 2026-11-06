#!/usr/bin/env bash
# ==============================================================================
#   NewFoobot - Foobot Network Configuration Launcher for macOS (EN / IT)
# ==============================================================================

# Move to the script's current directory
cd "$(dirname "$0")"

# Clear the Terminal screen
clear

echo "================================================================================"
echo "  NEWFOOBOT - Foobot Network Management & Setup Tool (macOS)"
echo "================================================================================"
echo ""
echo "Checking execution environment..."
echo ""

# Check for Python 3 (standard on macOS)
if command -v python3 &>/dev/null; then
    python3 foobot_manager.py
elif command -v python &>/dev/null; then
    python foobot_manager.py
else
    echo "================================================================================"
    echo " [ERROR] Python 3 was not found on your Mac."
    echo "================================================================================"
    echo ""
    echo "EN: To install Apple's free official Python tools, open Terminal and run:"
    echo "IT: Per installare gli strumenti Python gratuiti di Apple, digita nel Terminale:"
    echo "    xcode-select --install"
    echo ""
    read -p "Press ENTER to exit..."
    exit 1
fi
