#!/usr/bin/env python3
"""
Laravel Dev Server Launcher
- Start/stop XAMPP Apache & MySQL services
- Run php artisan serve with custom host & port
"""

import os
import sys
import subprocess
import time
import signal
import json
import re
from pathlib import Path

PROJECT_DIR = Path(__file__).parent.resolve()
XAMPP_DIR = Path("C:/xampp")
PHP_BIN = "php"
ENV_FILE = PROJECT_DIR / ".env"


def load_env_defaults():
    host, port = "127.0.0.1", "8000"
    if ENV_FILE.exists():
        content = ENV_FILE.read_text(encoding="utf-8")
        m = re.search(r'^APP_URL=http://([^:]+):(\d+)', content, re.M)
        if m:
            host, port = m.group(1), m.group(2)
    return host, port


def run_bat(bat_name, wait=False):
    path = XAMPP_DIR / bat_name
    if not path.exists():
        print(f"  [!] Not found: {path}")
        return None
    info = subprocess.STARTUPINFO()
    info.dwFlags |= subprocess.STARTF_USESHOWWINDOW
    info.wShowWindow = 0
    proc = subprocess.Popen(
        [str(path)],
        startupinfo=info,
        stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        text=True, cwd=str(XAMPP_DIR)
    )
    if wait:
        out, err = proc.communicate(timeout=10)
        print(out.strip())
        if err.strip():
            print(f"  [!] {err.strip()}")
    return proc


def is_service_running(name):
    try:
        r = subprocess.run(
            ["tasklist", "/FI", f"IMAGENAME eq {name}.exe"],
            capture_output=True, text=True, timeout=5
        )
        return name.lower() in r.stdout.lower()
    except Exception:
        return False


def print_banner():
    print("=" * 55)
    print("   Laravel Dev Server Launcher — e-DTR Records")
    print("=" * 55)


def print_status():
    apache = is_service_running("httpd") or is_service_running("Apache.exe")
    mysql = is_service_running("mysqld") or is_service_running("mysql.exe")
    print(f"\n  XAMPP Status:")
    print(f"    Apache: {'RUNNING' if apache else 'STOPPED'}")
    print(f"    MySQL:  {'RUNNING' if mysql else 'STOPPED'}")
    return apache, mysql


def menu_start_services():
    apache, mysql = print_status()
    print(f"\n  [1] Start Apache")
    print(f"  [2] Start MySQL")
    print(f"  [3] Start Both")
    print(f"  [4] Stop Apache")
    print(f"  [5] Stop MySQL")
    print(f"  [6] Stop Both")
    print(f"  [0] Continue without changes")
    choice = input("\n  Select [0-6]: ").strip()
    if choice == "1":
        print("  Starting Apache...")
        run_bat("apache_start.bat")
    elif choice == "2":
        print("  Starting MySQL...")
        run_bat("mysql_start.bat")
    elif choice == "3":
        print("  Starting Apache & MySQL...")
        run_bat("apache_start.bat")
        run_bat("mysql_start.bat")
    elif choice == "4":
        print("  Stopping Apache...")
        run_bat("apache_stop.bat")
    elif choice == "5":
        print("  Stopping MySQL...")
        run_bat("mysql_stop.bat")
    elif choice == "6":
        print("  Stopping Apache & MySQL...")
        run_bat("apache_stop.bat")
        run_bat("mysql_stop.bat")
    else:
        print("  No changes.")
    if choice in ("1", "2", "3", "4", "5", "6"):
        time.sleep(1)
        print_status()


def prompt_host_port():
    default_host, default_port = load_env_defaults()
    print(f"\n  Enter host & port for php artisan serve")
    host = input(f"  Host [{default_host}]: ").strip() or default_host
    port = input(f"  Port [{default_port}]: ").strip() or default_port
    return host, port


def run_artisan_serve(host, port):
    print(f"\n  Starting: php artisan serve --host={host} --port={port}")
    print(f"  URL: http://{host}:{port}")
    print(f"  Press Ctrl+C to stop the server.\n")
    cmd = [PHP_BIN, "artisan", "serve", f"--host={host}", f"--port={port}"]
    proc = subprocess.Popen(cmd, cwd=str(PROJECT_DIR))
    try:
        proc.wait()
    except KeyboardInterrupt:
        print("\n  Shutting down...")
        proc.terminate()
        proc.wait()
        print("  Server stopped.")
        sys.exit(0)


def main():
    print_banner()

    while True:
        os.system("")  # enable ANSI on Windows
        print()
        menu_start_services()
        print()
        choice = input("  Continue to start the dev server? (y/n): ").strip().lower()
        if choice == "y":
            break
        print("  Okay, make your selections above.\n")

    host, port = prompt_host_port()
    print(f"\n  Confirming: http://{host}:{port}")
    try:
        run_artisan_serve(host, port)
    except FileNotFoundError:
        print("  [!] PHP not found. Make sure PHP is in your PATH.")
        sys.exit(1)


if __name__ == "__main__":
    main()
