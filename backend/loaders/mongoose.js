import mongoose from 'mongoose'

export default async () => {
  const connection = await mongoose.connect(
    'mongodb://root:example@localhost:27017/admin',
    {
      useNewUrlParser: true,
      useCreateIndex: true
    }
  )
  return connection.connection.db
}
