<template>
  <div>
    <h1>Training Dummy page</h1>

    <bar-with-table v-if="hits" :logs="hits" backgroundColor="#f87979" label="Damage" />
    <bar-with-table v-if="heals" :logs="heals" backgroundColor="#78f979" label="Heal" />

    <el-row v-if="!logs.length" :gutter="20">
      <el-col :span="10" :offset="7">
        <div style="margin-top: 15px;">
          <h2>Select an log file</h2>
          <el-upload
            action="tmp"
            :on-remove="removeFile"
            :on-change="onFileChange"
            :auto-upload="false"
          >
            <el-button slot="trigger" size="small" type="primary">Choose log file</el-button>
          </el-upload>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import AWS from "aws-sdk";
import BarWithTable from "../components/BarWithTable.vue";
import LogParser from "../logParser";
import POWER_NAMES from "../powerNames.json";

AWS.config.update({
  accessKeyId: "-",
  secretAccessKey: "-",
  region: "eu-central-1"
});
const bucketName = "combatlogs.crusaderw.com";
const s3 = new AWS.S3();

const getData = (logs, skillAction, { skillBy = null, skillTarget = null }) => {
  let startDate = null;
  let endDate = null;
  const logsBySkillNames = logs
    .filter(log => log.skillName && log.dateTime && log.skillAmount)
    .filter(log => log.skillAction === skillAction)
    .filter(log => (skillBy ? log.skillBy === skillBy : true))
    .filter(log => (skillTarget ? log.skillTarget === skillTarget : true))
    .map(log => ({ ...log, dateTime: log.dateTime.getTime() }))
    .reduce((groupedLogs, log) => {
      const accumulatedLog = groupedLogs[log.skillName] || {};

      startDate = startDate || log.dateTime;
      if (log.dateTime < startDate) {
        startDate = log.dateTime;
      }

      endDate = endDate || log.dateTime;
      if (log.dateTime > endDate) {
        endDate = log.dateTime;
      }

      const skillAmount = (+accumulatedLog.skillAmount || 0) + +log.skillAmount;

      return {
        ...groupedLogs,
        [log.skillName]: {
          skillName: log.skillName,
          skillAmount,
          count: (+accumulatedLog.count || 0) + 1,
          critCount: (+accumulatedLog.critCount || 0) + +log.skillCritical
        }
      };
    }, {});

  return Object.values(logsBySkillNames)
    .map(log => ({
      ...log,
      perSecond: (log.skillAmount / ((endDate - startDate) / 1000)).toFixed()
    }))
    .sort((a, b) => b.skillAmount - a.skillAmount);
};

export default {
  name: "Upload",
  components: { BarWithTable },
  data() {
    return {
      file: null,
      username: "",
      location: "",
      hits: [],
      heals: [],
      logs: []
    };
  },
  methods: {
    async uploadFiletoS3(file) {
      const params = {
        Bucket: bucketName,
        Key: `${Date.now()}-${file.name}`,
        Body: file
      };
      s3.upload(params, (err, data) => {
        if (err) {
          console.error(`Upload Error ${err}`);
          return;
        }
        console.log("Upload Completed");
      });
    },
    async onFileChange(file, fileList) {
      console.log(file);
      this.uploadFiletoS3(file.raw);

      this.file = await file.raw.text();
      this.logs = this.file.split("\n").map(log => {
        const logParser = new LogParser(
          log,
          this.location,
          this.username,
          POWER_NAMES
        );
        logParser.parse();
        return logParser.getDBData();
      });

      this.hits = getData(this.logs, LogParser.ACTION_TYPES.HIT, {
        skillBy: "Your"
      });
      this.heals = getData(this.logs, LogParser.ACTION_TYPES.HEAL, {
        skillTarget: "You"
      });
    },

    removeFile() {
      this.file = null;
    }
  },
  computed: {
    dataset: function() {
      return {
        labels: this.logs.map(log => log.skillName),
        datasets: [
          {
            label: "Damage",
            backgroundColor: "#f87979",
            data: this.logs.map(log => log.skillAmount)
          }
        ]
      };
    }
  }
};
</script>
