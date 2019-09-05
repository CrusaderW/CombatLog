import Fight from "./fight.mjs";

export default class LogsSplitter {
  constructor({ logs }) {
    this.logs = logs;
  }

  async splitByFights() {
    if (!this.logs.length) {
      console.warn("try to split by fights but logs are empty");
      return;
    }

    const fights = [];
    let currentFight = new Fight(this.logs[0].dateTime);
    for (let ii = 0; ii < this.logs.length; ii++) {
      const log = this.logs[ii];
      if (currentFight.isSameFight(log)) {
        currentFight.addLog(log);
      } else {
        fights.push(currentFight);
        currentFight = new Fight(log.dateTime);
      }
    }

    fights.forEach(fight => fight.fillTeams());

    return fights.map(fight => fight.getDBData());
  }
}