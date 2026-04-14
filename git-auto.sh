#!/bin/bash

while true; do
  echo "-----------------------------"

  commit_msg=$(whiptail --inputbox "Commit Nachricht:" 10 60 3>&1 1>&2 2>&3)

  branch=$(git branch --format="%(refname:short)" | fzf --prompt="🌿 Branch wählen: ")

  if [ -z "$branch" ] || [ -z "$commit_msg" ]; then
    echo "❌ Abgebrochen"
    continue
  fi

  git add .
  git commit -m "$commit_msg"
  git push origin "$branch"

  echo "✅ Gepusht auf $branch"
  echo ""

done
