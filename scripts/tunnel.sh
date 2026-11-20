#!/bin/bash
# Learnova LMS - Cloudflare Tunnel Helper

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$ROOT_DIR/tunnel.log"
PORT=8088

case "$1" in
    start)
        if pgrep -f "cloudflared tunnel.*$PORT" > /dev/null; then
            echo "Tunnel is already running!"
            URL=$(grep -oE "https://[a-zA-Z0-9.-]+\.trycloudflare\.com" "$LOG_FILE" | tail -n 1)
            echo "Current URL: $URL"
            exit 0
        fi

        echo "Starting Cloudflare Tunnel on port $PORT..."
        nohup cloudflared tunnel --url "http://localhost:$PORT" > "$LOG_FILE" 2>&1 &
        sleep 4

        URL=$(grep -oE "https://[a-zA-Z0-9.-]+\.trycloudflare\.com" "$LOG_FILE" | tail -n 1)
        if [ -n "$URL" ]; then
            echo "✅ Tunnel started successfully!"
            echo "🌐 Public URL: $URL"
        else
            echo "Tunnel started. Check log: tail -f $LOG_FILE"
        fi
        ;;

    stop)
        echo "Stopping Cloudflare Tunnel..."
        pkill -f "cloudflared tunnel.*$PORT" && echo "✅ Tunnel stopped." || echo "Tunnel was not running."
        ;;

    status)
        if pgrep -f "cloudflared tunnel.*$PORT" > /dev/null; then
            URL=$(grep -oE "https://[a-zA-Z0-9.-]+\.trycloudflare\.com" "$LOG_FILE" | tail -n 1)
            echo "✅ Tunnel is RUNNING"
            echo "🌐 Public URL: $URL"
        else
            echo "❌ Tunnel is STOPPED"
        fi
        ;;

    *)
        echo "Cara pakai:"
        echo "  ./scripts/tunnel.sh start   # Menjalankan tunnel di background"
        echo "  ./scripts/tunnel.sh status  # Melihat status & link aktif"
        echo "  ./scripts/tunnel.sh stop    # Mematikan tunnel"
        echo ""
        echo "Atau langsung jalankan foreground:"
        echo "  cloudflared tunnel --url http://localhost:8088"
        ;;
esac
