================================================================================
  QUICK START GUIDE FOR macOS (APPLE MAC / MACBOOK / IMAC / MAC MINI)
================================================================================

This package allows you to manage, diagnose, and reconfigure your Foobot device
directly from macOS, without needing Windows or the discontinued mobile app.

The interactive program supports both English (EN) and Italian (IT), automatically
detecting your system language at startup.

--------------------------------------------------------------------------------
1. HOW TO START THE PROGRAM WITH A DOUBLE CLICK:
--------------------------------------------------------------------------------
1. Double-click the file:
      START_FOOBOT_MANAGER.command

2. The macOS Terminal will open automatically and launch the interactive
   color-coded utility.

--------------------------------------------------------------------------------
2. macOS SECURITY NOTE (If you see "Cannot be opened" warning):
--------------------------------------------------------------------------------
If macOS displays a Gatekeeper security prompt or states that the file lacks
execution permissions (common with files downloaded from the Internet):

A) QUICK METHOD:
   Right-click (or Ctrl + Click) on "START_FOOBOT_MANAGER.command",
   select "Open" from the context menu, and confirm by clicking "Open".

B) VIA TERMINAL (To grant execution permissions):
   Open the macOS Terminal, navigate to this folder, and run:
      chmod +x START_FOOBOT_MANAGER.command
   Then press ENTER. From then on, the file will always open with a double-click.

--------------------------------------------------------------------------------
3. DIRECT COMMAND LINE LAUNCH (Alternative for advanced users):
--------------------------------------------------------------------------------
Open Terminal, navigate to this folder, and run:
   python3 foobot_manager.py

No external libraries are required (only standard Python 3, which is natively
supported on modern macOS systems).
