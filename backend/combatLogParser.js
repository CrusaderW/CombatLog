const fs = require("fs")
const fsPromises = fs.promises;

const ACTION_TYPE = {
  HIT: 'hit',
  HEAL: 'healed',
};

// const log = "2019-06-22T18:14:59.775Z INFO    COMBAT    - Combat _||_ Event=[Hithar Whirlwind hit You for 76  damage.] "

const getPowersNames = async () => {
  const powersDir = "./power";
  const powerNames = [];
  const powerFiles = await fsPromises.readdir(powersDir);

  await Promise.all(powerFiles.map(async powerFile => {
    const path = `${powersDir}/${powerFile}`;
    try {
      const file = await fsPromises.readFile(path, { encoding: "utf8" });
      const power = JSON.parse(file);
      if (power.name) {
        powerNames.push(power.name);
      }
    } catch (err) {
      console.warn(`[WARN] cannot read or get data from file: ${path}`, err);
    }
  }));

  fsPromises.writeFile('./tmp.json', JSON.stringify(powerNames, null, 2))

  return powerNames;
};

const getActionType = (eventStr) => {
  // order is importans because of "hit points" substring
  if (eventStr.includes(ACTION_TYPE.HEAL)) {
    return ACTION_TYPE.HEAL;
  }

  if (eventStr.includes(ACTION_TYPE.HIT)) {
    return ACTION_TYPE.HIT;
  }

  return null;
}

const getDateTime = (log) => new Date(log.split(' ')[0])

const getSplittedByActionType = (eventPart, actionType) =>
  eventPart.split(actionType).map(part => part.trim());

const isCritical = (eventPart) => eventPart.includes('(Critical)');

const getSkillName = (skillByAndSkillNamePart, powerNames = []) => {
  const splitted = skillByAndSkillNamePart.trim().split(' ').reverse();
  let skillName = splitted[0];

  for (let ind = 1; ind < splitted.length; ind++) {
    if (powerNames.includes(skillName)) {
      return skillName;
    }

    skillName = `${splitted[ind]} ${skillName}`
  }

  return powerNames.includes(skillName) ? skillName : null;
};

const getSkillBy = (skillByAndSkillNamePart, skillName) =>
  skillName ?
    skillByAndSkillNamePart.slice(0, skillByAndSkillNamePart.indexOf(skillName)).trim() :
    skillByAndSkillNamePart.trim();

const getSkillTarget = (skillTargetAndSkillAmountPart) =>
  skillTargetAndSkillAmountPart.split('for')[0].trim();

const getSkillAmount = (skillTargetAndSkillAmountPart) =>
  parseFloat(skillTargetAndSkillAmountPart.split('for')[1].trim());

const parseLog = async (line, powerNames) => {
  const actionType = getActionType(line);
  if (actionType === null) {
    console.warn('[WARN] line was skipped cause action type not defined')
    return;
  }

  const eventPart = line.split("Event=[")[1].trim().slice(0, -1);

  const splittedByActionType = getSplittedByActionType(eventPart, actionType);
  const skillByAndSkillNamePart = splittedByActionType[0];
  const skillTargetAndSkillAmountPart = splittedByActionType[1];
  console.log('splittedByActionType', splittedByActionType);

  const skillName = getSkillName(skillByAndSkillNamePart, powerNames)

  return {
    actionType,
    skillName,
    dateTime: getDateTime(line),
    skillBy: getSkillBy(skillByAndSkillNamePart, skillName),
    skillTarget: getSkillTarget(skillTargetAndSkillAmountPart),
    skillAmount: getSkillAmount(skillTargetAndSkillAmountPart),
    skillCritical: isCritical(eventPart),
  }
};

const parseFile = async filename => {
  const powerNames = await getPowersNames();
  const logs = await fsPromises.readFile(filename, { encoding: "utf8" });
  return Promise.all(logs.split('\n').map((log) => parseLog(log, powerNames)));
};

module.exports = { parseFile };
