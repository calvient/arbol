import {Box} from '@calvient/decal';
import {
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  CartesianGrid,
  Legend,
} from 'recharts';
import {stringToColor} from '../../../../../Utils/stringToColor';
import {extractTruncationMeta} from '../../../../../Utils/chartTruncation';
import TruncationWarning from './TruncationWarning';

interface LineGraphFormatProps {
  data: Array<{name: string; value: number}>;
  isPercentage?: boolean;
}

const formatNumber = (value: number, isPercentage?: boolean) => {
  const formatted = value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return isPercentage ? `${formatted}%` : formatted;
};

const LineFormat = ({data, isPercentage}: LineGraphFormatProps) => {
  const {chartData, meta} = extractTruncationMeta(data);
  const keys = Object.keys(chartData[0]).filter((key) => key !== 'name');

  return (
    <>
      {meta.isTruncated && <TruncationWarning total={meta.total} shown={meta.shown} />}
      <Box mt={4} w={'full'} h={'400px'}>
        <ResponsiveContainer width='100%' height='100%'>
          <LineChart width={400} height={400} data={chartData}>
            {keys.length > 1 && <Legend />}
            <CartesianGrid strokeDasharray='3 3' />
            <XAxis dataKey='name' />
            <YAxis
              domain={
                isPercentage
                  ? [0, 100]
                  : [
                      (dataMin: number) => Math.floor(dataMin),
                      (dataMax: number) => Math.ceil(dataMax),
                    ]
              }
              tickFormatter={(value: number) => formatNumber(value, isPercentage)}
            />
            <Tooltip formatter={(value: number) => formatNumber(value, isPercentage)} />
            {keys.map((key) => (
              <Line
                key={key}
                type='monotone'
                dataKey={key}
                stroke={stringToColor(key)}
                strokeWidth={3}
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </Box>
    </>
  );
};

export default LineFormat;
