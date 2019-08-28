const fs = require("fs")
const fsPromises = fs.promises;

const ACTION_TYPE = {
  HIT: 'hit',
  HEAL: 'healed',
};

const log = "2019-06-22T18:14:59.775Z INFO    COMBAT    - Combat _||_ Event=[Hithar Whirlwind hit You for 76  damage.] "

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
  if (eventStr.includes(ACTION_TYPE.HIT)) {
    return ACTION_TYPE.HIT;
  }

  if (eventStr.includes(ACTION_TYPE.HEAL)) {
    return ACTION_TYPE.HEAL;
  }

  return null;
}

const getDateTime = (log) => new Date(log.split(' ')[0])

const getSplittedByActionType = (eventPart, actionType) =>
  eventPart.split(actionType).map(part => part.trim());

const isCritical = (eventPart) => eventPart.includes('(Critical)');

const getSkillName = (skillByAndSkillNamePart, powerNames = []) => {
  const splitted = skillByAndSkillNamePart.trim().split(' ').reverse();

  for (let ind = 0, skillName = splitted[0]; ind < splitted.length; ind++) {
    if (powerNames.includes(skillName)) {
      return skillName;
    }

    skillName = `${skillName} ${splitted[ind]}`
  }

  return null;
};

const getSkillBy = (skillByAndSkillNamePart, skillName) =>
  skillByAndSkillNamePart.slice(0, skillByAndSkillNamePart.indexOf(skillName)).trim();

const getSkillTarget = (skillTargetAndSkillAmountPart) =>
  skillTargetAndSkillAmountPart.split('for')[0].trim();


const getSkillAmount = (skillTargetAndSkillAmountPart) =>
  parseFloat(skillTargetAndSkillAmountPart.split('for')[1].trim());

(async () => {
  const powerNames = await getPowersNames();

  const eventPart = log.split("Event=[")[1].trim().slice(0, -1);
  const actionType = getActionType(eventPart);
  const splittedByActionType = getSplittedByActionType(eventPart, actionType);
  const skillByAndSkillNamePart = splittedByActionType[0];
  const skillTargetAndSkillAmountPart = splittedByActionType[1];

  const skillName = getSkillName(skillByAndSkillNamePart, powerNames)

  return {
    actionType,
    skillName,
    dateTime: getDateTime(log),
    skillBy: getSkillBy(skillByAndSkillNamePart, skillName),
    skillTarget: getSkillTarget(skillTargetAndSkillAmountPart),
    skillAmount: getSkillAmount(skillTargetAndSkillAmountPart),
    skillCritical: isCritical(eventPart),
  }
})()
