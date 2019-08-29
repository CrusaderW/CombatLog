import mongoose from 'mongoose'

mongoose.connect('mongodb://root:example@localhost:27017/admin', { useNewUrlParser: true });

export const CombatLog = mongoose.model("CombatLog", mongoose.Schema({
  // user_id bigint,
  // skill_id bigint,
  // poi_id bigint
  skillAction: String,
  skillName: String,
  dateTime: Date,
  skillBy: String,
  skillTarget: String,
  skillAmount: Number,
  skillCritical: Boolean,
}));
