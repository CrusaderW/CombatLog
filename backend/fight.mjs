import { ACTION_TYPES, SKILL_BY_ME, SKILL_TARGET_ME } from "./constants.mjs";

// class not ready, work in progress
export default class Fight {
  // logs [];
  constructor(datetimeStart) {
    this.datetimeStart = datetimeStart;
    this.datetimeEnd = datetimeStart;
    this.logs = [];
    this.fightsThreshold = 1000 * 5 * 60;
    this.location = {
      campaign: null,
      zone: null,
      POI: null
    };
    // poi_id bigint,
    // date_time_start timestamp without time zone,
    // date_time_end timestamp without time zone,
    // line_nr integer,
    // submitters text,
    // team_alpha text,
    // team_bravo text
  }

  getDBData() {
    return {
      logs: this.logs,
      location: this.location,
      datetimeStart: this.datetimeStart,
      datetimeEnd: this.datetimeEnd,
      teams: this.teams
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
    this.logs = [...this.logs, log];

    if (log.dateTime > this.datetimeEnd) {
      this.datetimeEnd = log.dateTime;
    }

    if (log.dateTime < this.datetimeStart) {
      this.datetimeStart = log.dateTime;
    }
  }

  fillTeams() {
    if (!this.logs.length) {
      console.warn("try to fill teams but logs are empty");
      return;
    }
    const teamAlpha = new Set();
    const teamBravo = new Set();
    teamAlpha.add(this.logs[0].username);
    this.logs.forEach(log => {
      if (log.skillBy === SKILL_BY_ME && log.skillTarget === SKILL_TARGET_ME) {
        return;
      }
      if (log.skillAction === ACTION_TYPES.HIT) {
        if (log.skillBy === SKILL_BY_ME) {
          teamBravo.add(log.skillTarget);
        } else if (log.skillTarget === SKILL_TARGET_ME) {
          teamBravo.add(log.skillBy);
        }
      } else if (log.skillAction === ACTION_TYPES.HEAL) {
        if (log.skillBy === SKILL_BY_ME) {
          teamAlpha.add(log.skillTarget);
        } else if (log.skillTarget === SKILL_TARGET_ME) {
          teamAlpha.add(log.skillBy);
        }
      }
    });
    this.teams = [Array.from(teamAlpha), Array.from(teamBravo)];
  }
}
