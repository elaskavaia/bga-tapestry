#!/bin/bash
# Copy the project it sits in into a renamed copy: usage rename.sh [taptest|tapestry] [dest]
#
# Tapestry -> taptest, run from this repo:
#   misc/rename.sh                                                    # dest defaults to ../taptest
#   misc/rename.sh taptest ~/Develop/bga/bga-git/games/tapestry/taptest
#
# taptest -> Tapestry, run from the taptest checkout (CUR_PROJ is this script's own project):
#   misc/rename.sh tapestry                                           # dest defaults to ../tapestry
#   misc/rename.sh tapestry ~/git/bga-tapestry
#
# The sync prunes: files dropped from the source are removed from dest.
PROJECT_NAME=${1:-taptest}

TEMP_PROJ=/tmp/$PROJECT_NAME
CUR_PROJ=$(dirname $0)/..
CUR_PROJ=$(cd $CUR_PROJ;pwd)
RES_DIR=${2:-$CUR_PROJ/../$PROJECT_NAME}
rm -rf ${TEMP_PROJ}

if [ "$PROJECT_NAME" = "taptest" ]; then
    php8.4 ~/git/bga-sharedcode/misc/bgaprojectrename.php ${CUR_PROJ} ${TEMP_PROJ} --old-name Tapestry --new-name taptest
	sed -i ${TEMP_PROJ}/gameinfos.inc.php -E -e 's/(.game_name.) => .*/\1 => "Tapestry Test",/'
elif [ "$PROJECT_NAME" = "tapestry" ]; then
    php8.4 ~/git/bga-sharedcode/misc/bgaprojectrename.php ${CUR_PROJ} ${TEMP_PROJ} --old-name taptest --new-name Tapestry
	sed -i ${TEMP_PROJ}/gameinfos.inc.php -E -e 's/(.game_name.) => .*/\1 => "Tapestry",/'
fi
grep game_name ${TEMP_PROJ}/gameinfos.inc.php 
rsync -a --delete ${TEMP_PROJ}/ ${RES_DIR}/
