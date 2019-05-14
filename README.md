# DesertBot-Log
Scripts for hosting and serving [DesertBot](https://github.com/DesertBot/DesertBot)'s logs

## Setup
### Docker
This relies on [StarlitGhost](https://github.com/StarlitGhost)'s [base docker services](https://github.com/StarlitGhost/selfhost-base).

You'll want to symlink that project's .env file into this project's directory and set `DOMAIN_DBCO` and `LOG_DIR` in it.
Once that's done you can just spin it up with `docker-compose up -d`.

### Alternative Setup
You'll need to change the log path from `/logpath/` to the actual path to your logs in index.php, probably.
Otherwise, this is just a PHP page, so if you're already running something that serves PHP (apache, nginx, etc) it should Just Work™
