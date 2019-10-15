import mongoose from 'mongoose'
import env from '../env.js'

export default async () => {
  const connection = await mongoose.connect(env.MONGO_URL, {
    useNewUrlParser: true,
    useCreateIndex: true
  })
  return connection.connection.db
}
