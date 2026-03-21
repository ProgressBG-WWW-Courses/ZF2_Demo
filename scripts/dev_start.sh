#!/usr/bin/env bash
# dev-start.sh — start ZF2 dev environment

set -e

# --- Config ---
CONTAINER_APP="zf2_demo-app-1"
CONTAINER_DB="zf2_demo-db-1"
NGROK_PORT=8088         
NGROK_LOG="/tmp/ngrok.log"
NGROK_DOMAIN="unmajestic-decussately-teresia.ngrok-free.dev"

# --- Start containers ---
echo "Starting $CONTAINER_DB..."
docker start "$CONTAINER_DB"

echo "Starting $CONTAINER_APP..."
docker start "$CONTAINER_APP"

# --- Start ngrok in background ---
echo "Starting ngrok on port $NGROK_PORT..."
ngrok http --domain=$NGROK_DOMAIN "$NGROK_PORT" > "$NGROK_LOG" 2>&1 &
NGROK_PID=$!

# Wait a moment for ngrok to establish the tunnel
sleep 3

# --- Print the public URL ---
NGROK_URL=$(curl -s http://localhost:4040/api/tunnels \
  | grep -o '"public_url":"https://[^"]*"' \
  | head -1 \
  | cut -d'"' -f4)

if [ -n "$NGROK_URL" ]; then
  echo ""
  echo "✓ All services started."
  echo "✓ ngrok public URL: $NGROK_URL"
  echo ""
  echo "Use this as your Revolut webhook base URL:"
  echo "  $NGROK_URL/your-webhook-path"
else
  echo "⚠ ngrok started (PID $NGROK_PID) but URL not detected yet."
  echo "  Check http://localhost:4040 in your browser."
fi