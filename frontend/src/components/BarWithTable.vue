<template>
  <el-row v-if="logs.length" :gutter="20">
    <el-col :span="12">
      <bar-chart :dataset="chartData" />
    </el-col>
    <el-col :span="12" style="margin-top: 30px;">
      <el-table :data="logs" height="670" border>
        <el-table-column prop="skillName" label="Skill Name"></el-table-column>
        <el-table-column prop="skillAmount" label="Skill Amount"></el-table-column>
        <el-table-column prop="perSecond" label="Per Second"></el-table-column>
        <el-table-column prop="count" label="Usage count"></el-table-column>
        <el-table-column prop="critCount" label="Criticals count"></el-table-column>
      </el-table>
    </el-col>
  </el-row>
</template>
<script>
import BarChart from "./BarChart";

export default {
  name: "BarWithTable",
  components: {
    BarChart
  },
  props: {
    logs: Array,
    backgroundColor: String,
    label: String
  },
  computed: {
    tabelData: function() {
      return this.logs;
    },
    chartData: function() {
      return {
        labels: this.logs.map(log => log.skillName),
        datasets: [
          {
            label: this.label,
            backgroundColor: this.backgroundColor,
            data: this.logs.map(log => log.skillAmount)
          }
        ]
      };
    }
  }
};
</script>
