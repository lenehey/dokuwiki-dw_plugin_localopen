# DokuWiki Plugin: localopen

Open local files and folders directly from DokuWiki pages — **without browser popups or protocol handlers**.

This plugin replaces `localexplorer://`-style links with a modern, secure approach using a local HTTP service and background requests (`fetch()`), resulting in a seamless, one-click experience.

---

## ✨ Features

- Open local files (`.docx`, `.pdf`, etc.) and folders from wiki links
- No browser confirmation dialogs
- No blank tabs or navigation away from the wiki
- Works with:
  - Local drives (`C:\`, `Z:\`, etc.)
  - Network shares (`\\server\share`)
- Uses standard HTTP instead of custom protocol handlers
- Lightweight and fast

---

## 🧠 How It Works

1. The plugin renders links like:  \[\[localopen>c:\windows|Windows\]\]

2. When clicked:
- A background `fetch()` request is sent to:

  ```
  http://127.0.0.1:2222/open?path=...&token=...
  ```
- A small Python service running locally receives the request
- The service opens the file using Windows (`os.startfile`)

👉 The browser never navigates away from the page.

---

## 📦 Requirements

- DokuWiki
- Python 3 (Windows)
- A local Python service (included below)

---

## 🔧 Installation

### 1. Install the Plugin

Copy the plugin into:  lib/plugins/localopen/

---

### 2. Set Your Token

Choose a secret token (any random string)


Update it in:

- the plugin PHP file
- the Python service

---

### 3. Run the Local Python Service

1. Save this as `localopen_service.py`:

```python
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import urlparse, parse_qs, unquote
import os
import re
import sys
import subprocess
import time

HOST = "127.0.0.1"
PORT = 2222
TOKEN = "PUT-YOUR-TOKEN-HERE"

def normalize_path(path: str) -> str:
    path = path.strip().strip('"').strip("'")

    # Decode URL encoding first
    path = unquote(path)

    # Replace forward slashes with backslashes (Windows-native)
    path = path.replace("/", "\\")

    return path

def is_allowed(path: str) -> bool:
    # Allow any drive letter like C:\, D:\, H:\, etc.
    if re.match(r'^[a-zA-Z]:\\', path):
        return True

    # Allow UNC paths (network shares)
    if path.startswith('\\\\'):
        return True

    return False


def focus_window(title):
    subprocess.Popen([
        # "powershell",
        "-NoProfile",
        "-Command",
        f"$wshell = New-Object -ComObject wscript.shell; $wshell.AppActivate('{title}')"
    ])


import os
import subprocess

def open_path(path):
    ext = os.path.splitext(path)[1].lower()

    if os.path.isdir(path):
        subprocess.Popen(["explorer.exe", path])
        time.sleep(0.5)
        focus_window("File Explorer")
    else:
        os.startfile(path)
        time.sleep(1)

        if ext in [".xls", ".xlsx", ".xlsm"]:
            focus_window("Excel")
        elif ext == ".pdf":
            focus_window(os.path.basename(path))

class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        parsed = urlparse(self.path)

        if parsed.path != "/open":
            self.send_response(404)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.end_headers()
            self.wfile.write(b"Not found")
            return

        qs = parse_qs(parsed.query)
        token = qs.get("token", [""])[0]
        raw_path = qs.get("path", [""])[0]
        path = normalize_path(unquote(raw_path))

        if token != TOKEN:
            self.send_response(403)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.end_headers()
            self.wfile.write(b"Forbidden")
            return

        if not path:
            self.send_response(400)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.end_headers()
            self.wfile.write(b"Missing path")
            return

        if not is_allowed(path):
            self.send_response(403)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.end_headers()
            self.wfile.write(b"Path not allowed")
            return

        # Convert to Windows path for existence check
        win_path = path.replace("/", "\\")

        if not os.path.exists(win_path):
            self.send_response(404)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.end_headers()
            self.wfile.write(f"Path does not exist: {win_path}".encode("utf-8"))
            return

        try:
            open_path(path)
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.end_headers()
            self.wfile.write(b"""<html><body>Opened.</body></html>""")
        except Exception as e:
            self.send_response(500)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.end_headers()
            self.wfile.write(f"Error: {e}".encode("utf-8"))

    def log_message(self, format, *args):
        # Stay silent
        pass

def main():
    try:
        server = HTTPServer((HOST, PORT), Handler)
    except OSError as e:
        sys.exit(f"Could not bind to {HOST}:{PORT}: {e}")

    server.serve_forever()

if __name__ == "__main__":
    main()
```

# Run the LocalOpen Python Service at Windows Login (Task Scheduler)

This guide configures your Python script to run **silently** when you log into Windows, using Task Scheduler.

---

## Prerequisites

- Python installed
- Your script (e.g., `local_linker.py`) working when run manually
- Path to `pythonw.exe` (important for no console window)

Example Python path:  C:\Users\me\AppData\Local\Programs\Python\Python310\pythonw.exe


---

## Step 1 — Open Task Scheduler

1. Press `Win + R`
2. Enter:  `taskschd.msc`
3. Press Enter

---

## Step 2 — Create a New Task

1. In Task Scheduler, click: `Create Task`

> Do **not** use "Create Basic Task"

---

## Step 3 — Configure the General Tab

- **Name:** `LocalOpen Service`
- Select:
  - ✅ Run only when user is logged on
  - ✅ Run with highest privileges *(optional but recommended)*

---

## Step 4 — Configure the Trigger

1. Go to the **Triggers** tab
2. Click **New...**

Set:
- **Begin the task:** `At log on`
- **User:** Your user account

Click **OK**

---

## Step 5 — Configure the Action

1. Go to the **Actions** tab
2. Click **New...**

### Program/script
C:\Users\me\AppData\Local\Programs\Python\Python310\pythonw.exe


### Add arguments
"C:\Users\me\OneDrive\Apps\General Python Scripts\local_linker.py"


### Start in
C:\Users\me\OneDrive\Apps\General Python Scripts


Click **OK**

---

## Step 6 — Save the Task

- Click **OK**
- Enter your Windows password if prompted

---

## Step 7 — Test the Task

1. In Task Scheduler:
   - Right-click the task
   - Click **Run**

2. Test a link in your wiki

---

## Verify It Is Running

Open PowerShell:

```powershell
Get-Process pythonw
