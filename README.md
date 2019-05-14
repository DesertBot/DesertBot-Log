# DesertBot-Log
Scripts for hosting and serving [DesertBot](https://github.com/DesertBot/DesertBot)'s logs

## Setup
### Docker
This relies on [StarlitGhost](https://github.com/StarlitGhost)'s [base docker services](https://github.com/StarlitGhost/selfhost-base).

You'll want to symlink that project's .env file into this project's directory and set `DOMAIN_DBCO` in it.
Once that's done you can just spin it up with `docker-compose up -d`.

### Alternative Setup
This is just a PHP page, so if you're already running something that serves PHP (apache, nginx, etc) it should Just Work™
