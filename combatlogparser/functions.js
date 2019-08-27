window.onerror = function(msg, url, linenumber) {
    alert('Error message: '+msg+'\nURL: '+url+'\nLine Number: '+linenumber);
    return true;
}

// ARRAY SORTING
/* 
const data = [{
        years: 15,
        name: 'David',
        age: 28
    }, {
        years: 20,
        name: 'Joe',
        age: 23
    }, {
        years: 12,
        name: 'Tracy',
        age: 28
    }, {
        years: 18,
        name: 'Joel',
        age: 25
    }, {
        years: 19,
        name: 'Michael',
        age: 40
    }, {
        years: 11,
        name: 'Arnold',
        age: 35
    }, {
        years: 15,
        name: 'Paul',
        age: 24
    }, ];

    console.log('sorted by Age (desc), Name (asc), Years (desc):', multiSort(data, {
        age: 'desc',
        name: 'asc',
        years: 'desc'
    }));
*/

    /**
     * Sorts an array of objects by column/property.
     * @param {Array} array - The array of objects.
     * @param {object} sortObject - The object that contains the sort order keys with directions (asc/desc). e.g. { age: 'desc', name: 'asc' }
     * @returns {Array} The sorted array.
     */
    function multiSort(array, sortObject = {}) {
        const sortKeys = Object.keys(sortObject);

        // Return array if no sort object is supplied.
        if (!sortKeys.length) {
            return array;
        }

        // Change the values of the sortObject keys to -1, 0, or 1.
        for (let key in sortObject) {
            sortObject[key] = sortObject[key] === 'desc' || sortObject[key] === -1 ? -1 : (sortObject[key] === 'skip' || sortObject[key] === 0 ? 0 : 1);
        }

        const keySort = (a, b, direction) => {
            direction = direction !== null ? direction : 1;

            if (a === b) { // If the values are the same, do not switch positions.
                return 0;
            }

            // If b > a, multiply by -1 to get the reverse direction.
            return a > b ? direction : -1 * direction;
        };

        return array.sort((a, b) => {
            let sorted = 0;
            let index = 0;

            // Loop until sorted (-1 or 1) or until the sort keys have been processed.
            while (sorted === 0 && index < sortKeys.length) {
                const key = sortKeys[index];

                if (key) {
                    const direction = sortObject[key];

                    sorted = keySort(a[key], b[key], direction);
                    index++;
                }
            }

            return sorted;
        });
    }
// END ARRAY SORTING

function pushIfNotExist(elem, arr){
    if (arr.indexOf(elem) === -1) {
        arr.push(elem);
    } 
    return arr;
}



function jsGetFightsAndPlayers(combatLogData){
    if (combatLogData.length >0){
        var fightNr=1;
        var fights=[],fight=[];
        var players=[], fplayers=[];
        var submitters=[];
        var from_time = Date.parse(combatLogData[0]['date_time']);
        fight.push({'start': combatLogData[0]['date_time']});
        for (var i=0, n=combatLogData.length; i < n; ++i ) {
            var to_time = Date.parse(combatLogData[i]['date_time']);
            var diff_time=Math.round(Math.abs(to_time - from_time) / 60);
            if (diff_time<5){//a break bigger than 5 min means it's a different fight
                from_time=to_time;
                submitters=pushIfNotExist(combatLogData[i]['user_id'],submitters);
            }else{
                fplayers.sort();
                players.push({'fightNr':{fplayers}});
                fplayers=[];
                fights.push({'fightNr':{'end': combatLogData[i-1]['date_time']}});
                submitters.sort();
                fights.push({fightNr:{'submitters': submitters}});
                submitters=[];
                submitters=pushIfNotExist(combatLogData[i]['user_id'],submitters);
                fightNr++;
                fights.push({fightNr:{'start': combatLogData[i]['date_time']}});
                from_time=to_time;
            }
            fplayers=pushIfNotExist(combatLogData[i]['skill_by'], fplayers);
            fplayers=pushIfNotExist(combatLogData[i]['skill_target'], fplayers);
        }
        fplayers.sort();
        players.push({fightNr:{fplayers}});
        //players[fightNr]= array_values($players[$fightNr]);//reset the keys
        fights.push({fightNr:{'end':combatLogData[i-1]['date_time']}});
        submitters.sort();
        fights.push({fightNr:{'submitters': submitters}});
        var ret=[];
        ret.push({'fights': fights,'players':players});
        return ret;
    }
}