#!/bin/bash
#rename
PROJECT_NAME=${1:-taptest}

TEMP_PROJ=/tmp/$PROJECT_NAME
CUR_PROJ=$(dirname $0)/..
CUR_PROJ=$(cd $CUR_PROJ;pwd)
RES_DIR=${2:-$CUR_PROJ/../$PROJECT_NAME}
rm -rf ${TEMP_PROJ}
php7.4 ~/git/bga-sharedcode/misc/bgaprojectrename.php ${CUR_PROJ} ${TEMP_PROJ}
if [ "$PROJECT_NAME" = "taptest" ]; then
	sed -i ${TEMP_PROJ}/gameinfos.inc.php -e "s/'game_name' => .*/'game_name' => 'Tapestry Test',/"
elif [ "$PROJECT_NAME" = "tapestry" ]; then
	sed -i ${TEMP_PROJ}/gameinfos.inc.php -e "s/'game_name' => .*/'game_name' => 'Tapestry',/"
fi
grep game_name ${TEMP_PROJ}/gameinfos.inc.php 
cp -r $TEMP_PROJ/* $RES_DIR/
