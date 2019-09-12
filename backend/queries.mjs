import { Fight } from './models/Fight.js'

export const getRelatedFights = async fightId => {
  const newFight = await Fight.findById(fightId)
  const tenMinutes = 1000 * 60 * 10
  const datetimeEndWithGap = new Date(
    newFight.datetimeEnd.getTime() + tenMinutes
  )
  const datetimeStartWithGap = new Date(
    newFight.datetimeStart.getTime() - tenMinutes
  )

  return Fight.find({
    published: true,
    'location.POI': newFight.location.POI,
    'location.zone': newFight.location.zone,
    'location.campaign': newFight.location.campaign,
    $or: [
      {
        datetimeEnd: {
          $lte: datetimeEndWithGap,
          $gte: datetimeStartWithGap
        }
      },
      {
        datetimeStart: {
          $lte: datetimeEndWithGap,
          $gte: datetimeStartWithGap
        }
      }
    ]
  })
}
