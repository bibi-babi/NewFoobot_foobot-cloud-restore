#!/usr/bin/env bash
# ==============================================================================
#   NewFoobot - Foobot Network Configuration Launcher for Linux (EN / IT)
# ==============================================================================

# Move to the script's current directory
cd "$(dirname "$0")"

# Clear the terminal screen
clear

echo "================================================================================"
echo "  NEWFOOBOT - Foobot Network Management & Setup Tool (Linux)"
echo "================================================================================"
echo ""
echo "Checking execution environment..."
echo ""

# Check for Python 3 (standard on all Linux distributions)
if command -v python3 &>/dev/null; then
    python3 foobot_manager.py
elif command -v python &>/dev/null; then
    python foobot_manager.py
else
    echo "================================================================================"
    echo " [ERROR] Python 3 is not installed."
    echo "================================================================================"
    echo ""
    echo "EN: Install Python 3 using your distribution's package manager:"
    echo "IT: Installa Python 3 eseguendo il comando per la tua distribuzione:"
    echo "  - Debian / Ubuntu / Mint / Raspberry Pi OS : sudo apt update && sudo apt install python3"
    echo "  - Fedora / RHEL / CentOS                  : sudo dnf install python3"
    echo "  - Arch Linux / Manjaro                    : sudo pacman -S python"
    echo ""
    read -p "Press ENTER to exit..."
    exit 1
fi
