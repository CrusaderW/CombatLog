import mongoose from 'mongoose'

export const CombatLogSchema = mongoose.Schema({
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
  skillCritical: Boolean
})

export const CombatLog =
  mongoose.model.CombatLog || mongoose.model('CombatLog', CombatLogSchema)
