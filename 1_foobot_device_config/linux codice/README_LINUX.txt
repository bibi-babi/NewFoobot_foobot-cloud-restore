================================================================================
  QUICK START GUIDE FOR LINUX (UBUNTU / DEBIAN / FEDORA / ARCH / RASPBERRY PI)
================================================================================

This package allows you to manage, diagnose, and reconfigure your Foobot device
directly from Linux operating systems, without depending on Windows or external apps.

The interactive program supports both English (EN) and Italian (IT), automatically
detecting your system language at startup.

--------------------------------------------------------------------------------
1. HOW TO START THE PROGRAM FROM TERMINAL:
--------------------------------------------------------------------------------
1. Open a terminal and navigate to this folder.

2. Grant execution permissions to the launcher script (required only once):
      chmod +x START_FOOBOT_MANAGER.sh

3. Run the launcher script:
      ./START_FOOBOT_MANAGER.sh

--------------------------------------------------------------------------------
2. DIRECT LAUNCH WITH PYTHON 3:
--------------------------------------------------------------------------------
Alternatively, you can run the Python script directly with:
      python3 foobot_manager.py

--------------------------------------------------------------------------------
3. SYSTEM REQUIREMENTS:
--------------------------------------------------------------------------------
- Python 3.6 or higher (pre-installed by default on 99% of Linux distributions).
- No external libraries required (uses only native standard library modules).
- Make sure your computer is connected to the same 2.4 GHz Wi-Fi network as
  your Foobot, or connected to the ad-hoc Hotspot broadcast by the Foobot
  (10.10.100.254).
