// class not ready, work in progress
export default class Fight {
  // logs [];
  constructor(datetimeStart) {
    this.datetimeStart = datetimeStart;
    this.datetimeEnd = datetimeStart;
    this.logs = [];
    // poi_id bigint,
    // date_time_start timestamp without time zone,
    // date_time_end timestamp without time zone,
    // line_nr integer,
    // submitters text,
    // team_alpha text,
    // team_bravo text
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
}
