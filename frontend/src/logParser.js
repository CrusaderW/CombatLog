export default class LogParser {
  static get ACTION_TYPES() {
    return {
      HIT: "hit",
      HEAL: "healed",
      DRAIN_DODGE: "drained"
    };
  }

  constructor(
    rowLog,
    location,
    username,
    powerNames = [],
    unrecognizedSkills = new Set()
  ) {
    this.rowLog = rowLog;
    this.location = location;
    this.username = username;
    this.powerNames = powerNames;
    this.unrecognizedSkills = unrecognizedSkills;
  }

  getDBData() {
    return {
      skillAction: this.skillAction,
      skillName: this.skillName,
      dateTime: this.dateTime,
      skillBy: this.skillBy,
      skillTarget: this.skillTarget,
      skillAmount: +this.skillAmount,
      skillCritical: this.skillCritical,
      username: this.username,
      location: this.location
    };
  }

  parse() {
    this.skillAction = this.getSkillAction();
    if (this.skillAction === null) {
      console.warn(
        "[WARN] line was skipped cause action type not defined",
        this.rowLog
      );
      this.error = {
        msg: "[WARN] line was skipped cause action type not defined",
        row: this.rowLog
      };
      return;
    }

    const eventPart = this.rowLog
      .split("Event=[")[1]
      .trim()
      .slice(0, -1);
    const [
      skillByAndSkillNamePart,
      skillTargetAndSkillAmountPart
    ] = this.getSplittedBySkillAction(eventPart);

    this.skillName = this.getSkillName(skillByAndSkillNamePart);

    if (!this.skillName && skillByAndSkillNamePart !== "Your") {
      this.unrecognizedSkills.add(skillByAndSkillNamePart);
    }

    this.dateTime = this.getDateTime();
    this.skillBy = this.getSkillBy(skillByAndSkillNamePart);
    this.skillTarget = this.getSkillTarget(skillTargetAndSkillAmountPart);
    this.skillAmount = this.getSkillAmount(skillTargetAndSkillAmountPart);
    this.skillCritical = this.isCritical(eventPart);
  }

  getSkillAction() {
    // order is importans because of "hit points" substring
    if (this.rowLog.includes(LogParser.ACTION_TYPES.HEAL)) {
      return LogParser.ACTION_TYPES.HEAL;
    }

    if (this.rowLog.includes(LogParser.ACTION_TYPES.DRAIN_DODGE)) {
      return LogParser.ACTION_TYPES.DRAIN_DODGE;
    }

    if (this.rowLog.includes(LogParser.ACTION_TYPES.HIT)) {
      return LogParser.ACTION_TYPES.HIT;
    }

    return null;
  }

  getSplittedBySkillAction(eventPart) {
    return eventPart.split(this.skillAction).map(part => part.trim());
  }

  getSkillName(skillByAndSkillNamePart) {
    const splitted = skillByAndSkillNamePart
      .trim()
      .split(" ")
      .reverse();
    if (splitted.length === 1) {
      return null;
    }

    let skillName = splitted[0];

    for (let ind = 1; ind < splitted.length; ind++) {
      if (this.powerNames.includes(skillName.toLowerCase())) {
        return skillName;
      }

      skillName = `${splitted[ind]} ${skillName}`;
    }

    return this.powerNames.includes(skillName.toLowerCase()) ? skillName : null;
  }

  getDateTime() {
    return new Date(this.rowLog.split(" ")[0]);
  }

  getSkillBy(skillByAndSkillNamePart) {
    return this.skillName
      ? skillByAndSkillNamePart
        .slice(0, skillByAndSkillNamePart.indexOf(this.skillName))
        .trim()
      : skillByAndSkillNamePart.trim();
  }

  getSkillTarget(skillTargetAndSkillAmountPart) {
    return skillTargetAndSkillAmountPart.split("for")[0].trim();
  }

  getSkillAmount(skillTargetAndSkillAmountPart) {
    return parseFloat(skillTargetAndSkillAmountPart.split("for")[1].trim());
  }

  isCritical(eventPart) {
    return eventPart.includes("(Critical)");
  }
}
