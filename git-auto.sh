#!/bin/bash

while true; do
  echo "-----------------------------"

  read -p "Commit Nachricht: " commit_msg

  echo ""
  echo "🌿 Wähle einen Branch:"

  branches=($(git branch --format="%(refname:short)"))

  select branch in "${branches[@]}"; do
    if [ -n "$branch" ]; then
      break
    else
      echo "❌ Ungültige Auswahl"
    fi
  done

  git add .
  git commit -m "$commit_msg"
  git push origin "$branch"

  echo "✅ Gepusht auf $branch"
  echo ""

  # 🔁 Abfrage ob weitermachen
  read -p "Noch ein Commit/Branch? (j/n): " answer

  if [[ "$answer" != "j" && "$answer" != "J" ]]; then
    echo "👋 Script beendet"
    break
  fi

done
