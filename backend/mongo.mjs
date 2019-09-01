import mongoose from "mongoose";

mongoose.connect("mongodb://root:example@mongo:27017/admin", {
  useNewUrlParser: true
});

export const CombatLog = mongoose.model(
  "CombatLog",
  mongoose.Schema({
    // user_id bigint,
    // skill_id bigint,
    // poi_id bigint

    // TODO: tmp fields, need to be replaced by links or structure
    location: String,
    username: String,

    logId: mongoose.Schema.Types.ObjectId,
    skillAction: String,
    skillName: String,
    dateTime: Date,
    skillBy: String,
    skillTarget: String,
    skillAmount: Number,
    skillCritical: Boolean
  })
);
