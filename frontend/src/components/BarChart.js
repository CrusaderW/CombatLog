import { HorizontalBar } from "vue-chartjs";

export default {
  name: "BarChart",
  extends: HorizontalBar,
  props: {
    dataset: Object
  },
  mounted() {
    this.renderChart(this.dataset, this.options);
  }
};
