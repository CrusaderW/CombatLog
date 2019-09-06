import mongoose from "mongoose";

mongoose.connect("mongodb://root:example@mongo:27017/admin", {
  useNewUrlParser: true
});

export const Fight = mongoose.model(
  "Fight",
  mongoose.Schema({
    logs: Array,
    location: {
      campaign: String,
      zone: String,
      POI: String
    },
    datetimeStart: Date,
    datetimeEnd: Date,
    teams: [[String]],
    published: Boolean
  })
);

export const CombatLog = mongoose.model(
  "CombatLog",
  mongoose.Schema({
    // user_id bigint,
    // skill_id bigint,
    // poi_id bigint

    // TODO: tmp fields, need to be replaced by links or structure
    username: String,

    logId: mongoose.Schema.Types.ObjectId,
    skillAction: String,
    skillName: String,
    dateTime: Date,
    skillBy: String,
    skillTarget: String,
    skillAmount: Number,
    skillCritical: Boolean,
    syncronized: Boolean
  })
);
