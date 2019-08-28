const { join } = require('path');
const polka = require('polka');
const serve = require('serve-static')(join(__dirname, 'public'));
const multer = require('multer')
const { parseFile } = require('./combatLogParser');

const { PORT = 8080 } = process.env;

const upload = multer({ dest: 'uploads/' })

polka()
  .use(serve)
  .post('/uploadLog', upload.single('file'), async (req, res) => {
    const parsedLog = await parseFile(req.file.path);
    res.end(JSON.stringify(parsedLog));
  })
  .listen(PORT, err => {
    if (err) throw err;
    console.log(`> Running on localhost:${PORT}`);
  });