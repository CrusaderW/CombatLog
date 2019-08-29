import fs from 'fs';

const fsPromises = fs.promises;

const ACTION_TYPE = {
  HIT: 'hit',
  HEAL: 'healed',
  DRAIN_DODGE: 'drained',
};

const getPowersNames = async () => {
  const powersDir = "./power";
  const powerNames = [
    // passive Toxins (see Diffusion)
    "poison toxin",
    "disease toxin",
    "nature toxin",

    // class-dependent powers
    "rapid fire",
    "dodge",
    "ricochet shot",
    "block",
    "retaliate",

    // missing powers
    "fire bolt",
    "crushing bolt",
    "magic breaker",
    "arcane shot",
    "seed banewood aura",
    "fall",
  ];
  const powerFiles = await fsPromises.readdir(powersDir);

  await Promise.all(powerFiles.map(async powerFile => {
    const path = `${powersDir}/${powerFile}`;
    try {
      const file = await fsPromises.readFile(path, { encoding: "utf8" });
      const power = JSON.parse(file);
      if (power.name) {
        powerNames.push(power.name
          .replace(/ III$/, " 3")
          .replace(/ II$/, " 2")
          .replace(/ I$/, " 1")
          .toLowerCase()
        );

      }
    } catch (err) {
      console.warn(`[WARN] cannot read or get data from file: ${path}`, err);
    }
  }));

  fsPromises.writeFile('./tmp.json', JSON.stringify(powerNames, null, 2))

  return powerNames;
};

const getSkillAction = (eventStr) => {
  // order is importans because of "hit points" substring
  if (eventStr.includes(ACTION_TYPE.HEAL)) {
    return ACTION_TYPE.HEAL;
  }

  if (eventStr.includes(ACTION_TYPE.DRAIN_DODGE)) {
    return ACTION_TYPE.DRAIN_DODGE;
  }

  if (eventStr.includes(ACTION_TYPE.HIT)) {
    return ACTION_TYPE.HIT;
  }

  return null;
}

const getDateTime = (log) => new Date(log.split(' ')[0])

const getSplittedBySkillAction = (eventPart, skillAction) =>
  eventPart.split(skillAction).map(part => part.trim());

const isCritical = (eventPart) => eventPart.includes('(Critical)');

const getSkillName = (skillByAndSkillNamePart, powerNames = []) => {
  const splitted = skillByAndSkillNamePart.trim().split(' ').reverse();
  if (splitted.length === 1) {
    return null;
  }

  let skillName = splitted[0];

  for (let ind = 1; ind < splitted.length; ind++) {
    if (powerNames.includes(skillName.toLowerCase())) {
      return skillName;
    }

    skillName = `${splitted[ind]} ${skillName}`
  }

  return powerNames.includes(skillName.toLowerCase()) ? skillName : null;
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
  const skillAction = getSkillAction(line);
  if (skillAction === null) {
    console.warn('[WARN] line was skipped cause action type not defined', line)
    return;
  }

  const eventPart = line.split("Event=[")[1].trim().slice(0, -1);

  const splittedBySkillAction = getSplittedBySkillAction(eventPart, skillAction);
  const skillByAndSkillNamePart = splittedBySkillAction[0];
  const skillTargetAndSkillAmountPart = splittedBySkillAction[1];

  const skillName = getSkillName(skillByAndSkillNamePart, powerNames)

  return {
    skillAction,
    skillName,
    dateTime: getDateTime(line),
    skillBy: getSkillBy(skillByAndSkillNamePart, skillName),
    skillTarget: getSkillTarget(skillTargetAndSkillAmountPart),
    skillAmount: getSkillAmount(skillTargetAndSkillAmountPart),
    skillCritical: isCritical(eventPart),
  }
};

export const parseFile = async filename => {
  const powerNames = await getPowersNames();
  const logs = await fsPromises.readFile(filename, { encoding: "utf8" });
  return Promise.all(logs.split('\n').map((log) => parseLog(log, powerNames)));
};
