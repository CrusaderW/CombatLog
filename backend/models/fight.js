import mongoose from 'mongoose'
import { CombatLogSchema } from './log.js'

export const FightSchema = mongoose.Schema({
  logs: [CombatLogSchema],
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

export const Fight =
  mongoose.model.Fight || mongoose.model('Fight', FightSchema)
