================================================================================
  QUICK START GUIDE FOR WINDOWS (WINDOWS 10 / WINDOWS 11)
================================================================================

This package allows you to manage, diagnose, and reconfigure your Foobot device
directly from Windows operating systems using a native PowerShell script,
without installing any external software, drivers, or libraries.

The interactive program supports both English (EN) and Italian (IT), automatically
detecting your system language at startup.

--------------------------------------------------------------------------------
1. HOW TO START THE PROGRAM:
--------------------------------------------------------------------------------
1. Simply double-click the file:
      START_FOOBOT_MANAGER.bat

2. A command window will automatically open, let you confirm your language
   (English / Italian), and scan for your Foobot on the local network (LAN)
   or in Factory Hotspot mode (10.10.100.254).

--------------------------------------------------------------------------------
2. ALTERNATIVE DIRECT POWERSHELL LAUNCH:
--------------------------------------------------------------------------------
You can also run the script directly from a PowerShell terminal:
      powershell -ExecutionPolicy Bypass -File .\foobot_manager.ps1

--------------------------------------------------------------------------------
3. SYSTEM REQUIREMENTS:
--------------------------------------------------------------------------------
- Windows 10 or Windows 11 (PowerShell is natively built into the OS).
- No external libraries or third-party tools required.
- Make sure your computer is connected to the same 2.4 GHz Wi-Fi network as
  your Foobot, or connected to the ad-hoc Hotspot broadcast by the Foobot
  (10.10.100.254).
