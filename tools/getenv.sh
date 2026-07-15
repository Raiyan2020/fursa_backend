#!/bin/bash
sudo docker exec fursa_web sh -c 'env' 2>/dev/null | grep -iE 'AWS_|GCS_|MEDIA|STORAGE|BUCKET|ENDPOINT|SIGNED' | sort
