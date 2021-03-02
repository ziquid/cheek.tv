#!/bin/bash
# build cheek on OVH3 or local

set -xe
DIRNAME=$(dirname "$0")
BASENAME=$(basename "$0")
FULLNAME="$DIRNAME/$BASENAME"

# Linux/non-apache?  sudo to apache
[ $(uname) == Linux ] && [ $(whoami) != apache ] && exec sudo su -l apache -s /bin/bash "$FULLNAME" "$@"

function update() {
  drush cr -l $1 || :
  drush updb -y -l $1
  drush cr -l $1 || :
  [ $(uname) == Linux ] && drush cim -y sync -l $1 || drush cim -y sync --partial -l $1
  drush cr -l $1
  drush cc views -l $1
}

# main()
cd $(dirname "$0")/..
git fetch

if [ "$1" != "" ]; then
  echo checking out branch $1...
  git co $1
  git pull
fi

composer.phar install

update cheek
update flabbierlime92

scripts/db-dump.sh
