# Mobile UI driver

The mobile UI driver opens this statistics page in an isolated Android emulator with generated
sample data. It is intended for phone layout checks; it does not connect to the live statistics
database or use a physical device.

## Requirements

The driver currently starts background processes on Windows. It requires PHP and the project's
Composer dependencies, plus an Android SDK installation with `adb`, the emulator, and an installed
Android Virtual Device that includes Chrome. The default AVD is `Pixel_9_Isolated`.

## Commands

Run commands from the project root:

```bat
tools\mobile_ui_driver.bat start
tools\mobile_ui_driver.bat open
tools\mobile_ui_driver.bat text
tools\mobile_ui_driver.bat screenshot C:\temp\statistics.png
tools\mobile_ui_driver.bat scroll down
tools\mobile_ui_driver.bat tap 540 1200
tools\mobile_ui_driver.bat back
tools\mobile_ui_driver.bat stop
```

`open` accepts an optional local path, such as `/` or `/?day=2026-11-19`. `text` prints each
distinct visible label and its screen bounds. Screenshots require an absolute path to a PNG file.
Tap coordinates are non-negative screen pixels. Scrolling supports `up` and `down`.

Always run `stop` when the check is complete. It stops the driver's emulator and PHP server, then
deletes `tmp/mobile_ui_driver/`, including its database and config. If startup fails, the driver
also attempts the same cleanup.

## Sample data

The generated SQLite database uses the schema from `contract/schema.json`. It contains the lists
`Today` and `Inbox`, with one row each hour from 06:00 through 20:00 for today and yesterday in
Europe/Berlin. Open and completed counts vary by hour to make the summary and chart visible. Run
`stop` followed by `start` to reset the sample data.

## Emulator and network safety

The default Android Virtual Device is `Pixel_9_Isolated`; set `MOBILE_UI_DRIVER_AVD` to select a
different installed AVD. The Android SDK is found through `ANDROID_HOME`, `ANDROID_SDK_ROOT`, or
`%LOCALAPPDATA%\Android\Sdk`. The emulator starts read-only with snapshot saving, audio, and boot
animation disabled. The driver chooses an unused even console port starting at 5580 and addresses
ADB commands only to that emulator serial. The PHP server uses a temporary port and the emulator
reaches it through Android's host bridge at `10.0.2.2`.

The driver's generated database and configuration live only under `tmp/mobile_ui_driver/`. The
server uses `RTM_STATS_CONFIG_DIR` to load that separate configuration. Neither the user's
`config/` nor the producer's statistics database is modified. Chrome first-run flags are written
inside the disposable emulator session.

## Limitations

- The driver supports Android Chrome only; it does not support iOS or physical devices.
- Android boot usually takes one to three minutes.
- `uiautomator` may not report text drawn inside the SVG chart. Inspect a screenshot for chart
  appearance.
- There is no text-entry command. Use the query string with `open` for URL-driven page states.
- The first end-to-end emulator run should be performed by hand on the development machine.
