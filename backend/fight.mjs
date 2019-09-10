import { ACTION_TYPES } from "./constants.mjs";

export default class Fight {
  constructor(datetimeStart) {
    this.datetimeStart = datetimeStart;
    this.datetimeEnd = datetimeStart;
    this.logs = [];
    this.teams = [];
    this.fightsThreshold = 1000 * 10 * 60;
    this.location = {
      campaign: null,
      zone: null,
      POI: null
    };
  }

  getDBData() {
    return {
      logs: this.logs,
      location: this.location,
      datetimeStart: this.datetimeStart,
      datetimeEnd: this.datetimeEnd,
      teams: this.teams.map(team => Array.from(team))
    };
  }

  isSameFight(log) {
    const logTime = log.dateTime.getTime();
    const endDelta = logTime - this.datetimeEnd.getTime();
    const startDelta = this.datetimeStart.getTime() - this.datetime;

    if (endDelta > this.fightsThreshold || startDelta > this.fightsThreshold) {
      return false;
    }
    return true;
  }

  addLog(log) {
    const duplicatedLog = this.logs.find(
      l =>
        l.dateTime.getTime() === log.dateTime.getTime() &&
        l.skillName === log.skillName &&
        l.skillAmount === log.skillAmount
    );
    if (!duplicatedLog) {
      duplicatedLog.syncronized = true;
      return;
    }

    this.logs = [...this.logs, log];

    if (log.dateTime > this.datetimeEnd) {
      this.datetimeEnd = log.dateTime;
    }

    if (log.dateTime < this.datetimeStart) {
      this.datetimeStart = log.dateTime;
    }

    return;
    // that part not ready
    if (!this.teams.length) {
      this.createTeamsFromLog(log);
      return;
    }

    const [teamAlpha, teamBravo, teamUnknown] = this.teams;

    if (log.skillAction === ACTION_TYPES.HEAL) {
      if (teamAlpha.has(log.skillBy) || teamAlpha.has(log.skillTarget)) {
        teamAlpha.add(log.skillBy);
        teamAlpha.add(log.skillTarget);
      } else if (teamBravo.has(log.skillBy) || teamBravo.has(log.skillTarget)) {
        teamBravo.add(log.skillBy);
        teamBravo.add(log.skillTarget);
      } else {
        teamUnknown.add(log.skillBy);
        teamUnknown.add(log.skillTarget);
      }
    } else if (log.skillAction === ACTION_TYPES.HIT) {
      if (teamAlpha.has(log.skillBy) || teamBravo.has(log.skillTarget)) {
        teamAlpha.add(log.skillBy);
        teamBravo.add(log.skillTarget);
      } else if (teamAlpha.has(log.skillTarget) || teamBravo.has(log.skillBy)) {
        teamAlpha.add(log.skillTarget);
        teamBravo.add(log.skillBy);
      } else {
        teamUnknown.add(log.skillBy);
        teamUnknown.add(log.skillTarget);
      }
    }
  }

  createTeamsFromLog(log) {
    const teamAlpha = new Set();
    const teamBravo = new Set();
    const teamUnknown = new Set();
    if (log.skillAction === ACTION_TYPES.HIT) {
      teamAlpha.add(log.skillBy);
      teamBravo.add(log.skillTarget);
    } else if (log.skillAction === ACTION_TYPES.HEAL) {
      teamAlpha.add(log.skillBy);
      teamAlpha.add(log.skillTarget);
    }
    this.teams = [teamAlpha, teamBravo, teamUnknown];
  }

  fillTeams() {
    if (!this.logs.length) {
      console.warn("try to fill teams but logs are empty");
      return;
    }
    const teamAlpha = new Set();
    const teamBravo = new Set();
    const { username } = this.logs[0];
    teamAlpha.add(username);
    this.logs.forEach(log => {
      if (log.skillBy === username && log.skillTarget === username) {
        return;
      }
      if (log.skillAction === ACTION_TYPES.HIT) {
        if (log.skillBy === username) {
          teamBravo.add(log.skillTarget);
        } else if (log.skillTarget === username) {
          teamBravo.add(log.skillBy);
        }
      } else if (log.skillAction === ACTION_TYPES.HEAL) {
        if (log.skillBy === username) {
          teamAlpha.add(log.skillTarget);
        } else if (log.skillTarget === username) {
          teamAlpha.add(log.skillBy);
        }
      }
    });
    this.teams = [teamAlpha, teamBravo];
  }
}
